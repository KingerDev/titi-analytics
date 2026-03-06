<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class NewActivityMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Collection $orders,
        public readonly Collection $registrations,
    ) {}

    public function envelope(): Envelope
    {
        $parts = [];

        if ($this->orders->isNotEmpty()) {
            $parts[] = $this->orders->count() . ' ' . ($this->orders->count() === 1 ? 'objednávka' : (in_array($this->orders->count(), [2, 3, 4]) ? 'objednávky' : 'objednávok'));
        }

        if ($this->registrations->isNotEmpty()) {
            $parts[] = $this->registrations->count() . ' ' . ($this->registrations->count() === 1 ? 'registrácia' : (in_array($this->registrations->count(), [2, 3, 4]) ? 'registrácie' : 'registrácií'));
        }

        $subject = 'Nová aktivita: ' . implode(', ', $parts);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.new-activity');
    }

    public function attachments(): array
    {
        return [];
    }
}
