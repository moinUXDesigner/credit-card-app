<?php

namespace App\Mail;

use App\Models\Card;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DueDateReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Card $card,
        public string $type,
        public string $date,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->type === 'due' ? 'payment due date' : 'statement date';

        return new Envelope(
            subject: "Reminder: {$this->card->card_name} {$label} on {$this->date}",
        );
    }

    public function content(): Content
    {
        $label = $this->type === 'due' ? 'payment due date' : 'statement generation date';

        return new Content(
            htmlString: sprintf(
                '<p>Your %s for <strong>%s</strong> (%s •••• %s) is on <strong>%s</strong>.</p>',
                $label,
                $this->card->card_name,
                $this->card->bank_name,
                $this->card->last_four_digits,
                $this->date,
            ),
        );
    }
}
