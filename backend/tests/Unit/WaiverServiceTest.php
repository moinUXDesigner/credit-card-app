<?php

namespace Tests\Unit;

use App\Models\Card;
use App\Services\WaiverService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class WaiverServiceTest extends TestCase
{
    private WaiverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WaiverService();
    }

    private function cardWith(array $attrs): Card
    {
        return new Card(array_merge([
            'waiver_spend_required' => 0,
            'waiver_spend_completed' => 0,
            'card_year_start_month' => 1,
        ], $attrs));
    }

    public function test_remaining_spend_never_negative_when_completed_exceeds_required(): void
    {
        $card = $this->cardWith(['waiver_spend_required' => 100000, 'waiver_spend_completed' => 150000]);

        $this->assertSame(0.0, $this->service->remainingSpend($card));
    }

    public function test_remaining_spend_calculates_difference(): void
    {
        $card = $this->cardWith(['waiver_spend_required' => 300000, 'waiver_spend_completed' => 210000]);

        $this->assertEqualsWithDelta(90000.0, $this->service->remainingSpend($card), 0.001);
    }

    public function test_progress_percentage_caps_at_100(): void
    {
        $card = $this->cardWith(['waiver_spend_required' => 100000, 'waiver_spend_completed' => 150000]);

        $this->assertEqualsWithDelta(100.0, $this->service->progressPercentage($card), 0.001);
    }

    public function test_progress_percentage_defaults_to_100_when_no_requirement(): void
    {
        $card = $this->cardWith(['waiver_spend_required' => 0, 'waiver_spend_completed' => 0]);

        $this->assertEqualsWithDelta(100.0, $this->service->progressPercentage($card), 0.001);
    }

    public function test_months_left_within_same_year(): void
    {
        // anniversary month November, current month June => 6 months left (Jun..Nov inclusive)
        $card = $this->cardWith(['card_year_start_month' => 11]);
        $now = Carbon::create(2026, 6, 15);

        $this->assertSame(6, $this->service->monthsLeft($card, $now));
    }

    public function test_months_left_wraps_across_year_boundary(): void
    {
        // current month November, anniversary month February (next year) => 4 months left (Nov..Feb inclusive)
        $card = $this->cardWith(['card_year_start_month' => 2]);
        $now = Carbon::create(2026, 11, 10);

        $this->assertSame(4, $this->service->monthsLeft($card, $now));
    }

    public function test_months_left_is_never_zero_in_anniversary_month(): void
    {
        $card = $this->cardWith(['card_year_start_month' => 6]);
        $now = Carbon::create(2026, 6, 20);

        $this->assertSame(1, $this->service->monthsLeft($card, $now));
    }

    public function test_suggested_monthly_spend_is_zero_when_waiver_already_met(): void
    {
        $card = $this->cardWith(['waiver_spend_required' => 100000, 'waiver_spend_completed' => 100000]);

        $this->assertSame(0.0, $this->service->suggestedMonthlySpend($card));
    }

    public function test_suggested_monthly_spend_divides_remaining_by_months_left(): void
    {
        $card = $this->cardWith([
            'waiver_spend_required' => 300000,
            'waiver_spend_completed' => 210000,
            'card_year_start_month' => 9,
        ]);
        $now = Carbon::create(2026, 6, 15); // 4 months left: Jun, Jul, Aug, Sep

        $this->assertEqualsWithDelta(22500.0, $this->service->suggestedMonthlySpend($card, $now), 0.01);
    }

    public function test_urgency_score_is_zero_when_waiver_already_met(): void
    {
        $card = $this->cardWith(['waiver_spend_required' => 100000, 'waiver_spend_completed' => 100000]);

        $this->assertSame(0.0, $this->service->urgencyScore($card));
    }

    public function test_urgency_score_increases_as_deadline_approaches(): void
    {
        $card = $this->cardWith([
            'waiver_spend_required' => 100000,
            'waiver_spend_completed' => 50000,
            'card_year_start_month' => 12,
        ]);

        $farScore = $this->service->urgencyScore($card, Carbon::create(2026, 1, 1));
        $nearScore = $this->service->urgencyScore($card, Carbon::create(2026, 11, 1));

        $this->assertGreaterThan($farScore, $nearScore);
    }

    public function test_cycle_start_is_this_years_anniversary_when_already_passed(): void
    {
        $card = $this->cardWith(['card_year_start_month' => 3]);
        $now = Carbon::create(2026, 6, 15);

        $this->assertTrue($this->service->cycleStart($card, $now)->isSameDay(Carbon::create(2026, 3, 1)));
    }

    public function test_cycle_start_falls_back_to_last_years_anniversary_when_upcoming(): void
    {
        $card = $this->cardWith(['card_year_start_month' => 9]);
        $now = Carbon::create(2026, 6, 15);

        $this->assertTrue($this->service->cycleStart($card, $now)->isSameDay(Carbon::create(2025, 9, 1)));
    }

    public function test_sum_entries_in_current_cycle_includes_only_entries_within_the_12_month_window(): void
    {
        $card = $this->cardWith(['card_year_start_month' => 9]);
        $now = Carbon::create(2026, 6, 15); // cycle: Sep 2025 - Aug 2026

        $entries = [
            (object) ['year' => 2025, 'month' => 8, 'amount_spent' => 99000], // before cycle
            (object) ['year' => 2025, 'month' => 9, 'amount_spent' => 10000], // cycle start
            (object) ['year' => 2026, 'month' => 6, 'amount_spent' => 20000], // within cycle
            (object) ['year' => 2026, 'month' => 8, 'amount_spent' => 5000],  // cycle end
            (object) ['year' => 2026, 'month' => 9, 'amount_spent' => 77000], // next cycle
        ];

        $this->assertEqualsWithDelta(35000.0, $this->service->sumEntriesInCurrentCycle($card, $entries, $now), 0.001);
    }
}
