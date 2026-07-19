<?php

namespace App\Mail;

use App\Models\Source;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SourceFailingMail extends Mailable
{
    public function __construct(public Source $source) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[Lake City Commons] Source failing: {$this->source->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.source-failing');
    }
}
