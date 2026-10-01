<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Website;
use App\Notifications\SiteCareAlert;
use Illuminate\Console\Command;

class NotifyOverdueBackups extends Command
{
    protected $signature = 'sitecare:notify-overdue-backups';

    protected $description = 'Notify website teams when a scheduled backup is overdue';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        Website::query()
            ->where('status', 'active')
            ->whereNotNull('backup_frequency_hours')
            ->whereNull('backup_overdue_notified_at')
            ->whereHas('organisation', fn ($query) => $query->where('status', 'active'))
            ->whereHas('latestBackupRecord')
            ->with(['organisation', 'technician', 'latestBackupRecord'])
            ->orderBy('id')
            ->chunkById(100, function ($websites) use ($now, &$sent) {
                foreach ($websites as $website) {
                    $backup = $website->latestBackupRecord;
                    if (!$backup || !in_array($backup->status, ['completed', 'verified'], true)) continue;
                    if ($backup->completed_at->isAfter($now->copy()->subHours($website->backup_frequency_hours))) continue;

                    $marked = Website::query()->whereKey($website->id)->whereNull('backup_overdue_notified_at')
                        ->update(['backup_overdue_notified_at' => $now]);
                    if (!$marked) continue;

                    $users = User::where('organisation_id', $website->organisation_id)->where('role', 'client')->get();
                    if ($website->technician) $users->push($website->technician);
                    $users = $users->merge(User::where('role', 'admin')->get())->unique('id');
                    foreach ($users as $user) {
                        $user->notify(new SiteCareAlert('Website backup overdue', $website->name.' has not had a successful backup within its expected schedule.'));
                    }
                    $sent += $users->count();
                }
            });

        $this->info("Queued {$sent} overdue-backup alerts.");
        return self::SUCCESS;
    }
}
