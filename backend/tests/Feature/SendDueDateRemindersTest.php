<?php

namespace Tests\Feature;

use App\Mail\DueDateReminder;
use App\Models\Card;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendDueDateRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reminder_for_card_with_due_date_in_window(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 6, 20));

        $user = User::factory()->create();
        Card::factory()->for($user)->create(['due_day' => 22, 'statement_day' => 15]);

        $this->artisan('reminders:send-due-dates')->assertSuccessful();

        Mail::assertSent(DueDateReminder::class, fn ($mail) => $mail->type === 'due');
        Mail::assertNotSent(DueDateReminder::class, fn ($mail) => $mail->type === 'statement');

        Carbon::setTestNow();
    }

    public function test_does_not_send_reminder_when_dates_are_far_away(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 6, 1));

        $user = User::factory()->create();
        Card::factory()->for($user)->create(['due_day' => 25, 'statement_day' => 20]);

        $this->artisan('reminders:send-due-dates')->assertSuccessful();

        Mail::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_skips_inactive_cards(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::create(2026, 6, 20));

        $user = User::factory()->create();
        Card::factory()->for($user)->create(['due_day' => 21, 'is_active' => false]);

        $this->artisan('reminders:send-due-dates')->assertSuccessful();

        Mail::assertNothingSent();

        Carbon::setTestNow();
    }
}
