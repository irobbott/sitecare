<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $inviteUrl,public string $role){}
    public function envelope():Envelope{return new Envelope(subject:'You are invited to SiteCare');}
    public function content():Content{return new Content(view:'emails.invitation',with:['inviteUrl'=>$this->inviteUrl,'role'=>$this->role]);}
}
