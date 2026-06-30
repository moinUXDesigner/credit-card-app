<?php

namespace App\Console\Commands;

use App\Mail\DueDateReminder;
use App\Models\Card;
use App\Services\DateOccurrenceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('reminders:send-due-dates')]
#[Description('Email users whose card due/statement date falls within the next 3 days')]
class SendDueDateReminders extends Command
{
    private const REMINDER_WINDOW_DAYS = 3;

    public function handle(DateOccurrenceService $dateOccurrenceService): int
    {
        $sent = 0;

        Card::with('user')->where('is_active', true)->each(function (Card $card) use ($dateOccurrenceService, &$sent) {
            foreach (['due' => $card->due_day, 'statement' => $card->statement_day] as $type => $dayOfMonth) {
                $occurrence = $dateOccurrenceService->nextOccurrence($dayOfMonth);

                if ($occurrence['days_until'] <= self::REMINDER_WINDOW_DAYS) {
                    Mail::to($card->user->email)->send(new DueDateReminder($card, $type, $occurrence['date']));
                    $sent++;
                }
            }
        });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
