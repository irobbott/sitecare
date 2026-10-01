<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SiteCareAlert;
use Illuminate\Console\Command;

class NotifyTicketTargets extends Command
{
    protected $signature = 'sitecare:notify-ticket-targets';

    protected $description = 'Notify staff when ticket response or resolution targets are near or overdue';

    public function handle(): int
    {
        $now = now();
        $windowEnd = $now->copy()->addHour();
        $tickets = Ticket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->where(function ($query) use ($now, $windowEnd) {
                $query->where(function ($response) use ($now, $windowEnd) {
                    $response->whereNull('first_response_at')->whereNotNull('response_due_at')
                        ->where(function ($due) use ($now, $windowEnd) {
                            $due->where(function ($soon) use ($now, $windowEnd) {
                                $soon->whereNull('response_target_warned_at')->whereBetween('response_due_at', [$now, $windowEnd]);
                            })->orWhere(function ($late) use ($now) {
                                $late->whereNull('response_target_overdue_at')->where('response_due_at', '<', $now);
                            });
                        });
                })->orWhere(function ($resolution) use ($now, $windowEnd) {
                    $resolution->whereNotIn('status', ['waiting_for_client'])->whereNotNull('resolution_due_at')
                        ->where(function ($due) use ($now, $windowEnd) {
                            $due->where(function ($soon) use ($now, $windowEnd) {
                                $soon->whereNull('resolution_target_warned_at')->whereBetween('resolution_due_at', [$now, $windowEnd]);
                            })->orWhere(function ($late) use ($now) {
                                $late->whereNull('resolution_target_overdue_at')->where('resolution_due_at', '<', $now);
                            });
                        });
                });
            })
            ->with(['assignee:id,name,email,role,notification_preferences', 'reporter:id,name,email,role,notification_preferences'])
            ->orderBy('id')
            ->limit(500)
            ->get();

        $sent = 0;
        foreach ($tickets as $ticket) {
            $sent += $this->notifyTarget($ticket, 'response', $now, $windowEnd);
            if ($ticket->status !== 'waiting_for_client') {
                $sent += $this->notifyTarget($ticket, 'resolution', $now, $windowEnd);
            }
        }

        $this->info("Queued {$sent} ticket target alerts.");
        return self::SUCCESS;
    }

    private function notifyTarget(Ticket $ticket, string $kind, $now, $windowEnd): int
    {
        $dueAt = $kind === 'response' ? $ticket->response_due_at : $ticket->resolution_due_at;
        if (!$dueAt || ($kind === 'response' && $ticket->first_response_at)) return 0;

        $overdue = $dueAt->isBefore($now);
        if (!$overdue && $dueAt->isAfter($windowEnd)) return 0;

        $marker = $kind.'_target_'.($overdue ? 'overdue' : 'warned').'_at';
        if ($ticket->{$marker}) return 0;

        $recipients = collect([$ticket->assignee])->filter();
        if ($recipients->isEmpty()) $recipients = User::where('role', 'admin')->get();
        if ($recipients->isEmpty()) return 0;

        $label = $kind === 'response' ? 'first response' : 'resolution';
        $title = $overdue ? ucfirst($label).' target overdue' : ucfirst($label).' target approaching';
        $message = $overdue
            ? "{$ticket->number} has passed its {$label} target."
            : "{$ticket->number} is within one hour of its {$label} target.";

        $updated = Ticket::query()->whereKey($ticket->id)->whereNull($marker)->update([$marker => $now]);
        if (!$updated) return 0;

        foreach ($recipients->unique('id') as $recipient) {
            $recipient->notify(new SiteCareAlert($title, $message, '/'));
        }

        return $recipients->unique('id')->count();
    }
}
