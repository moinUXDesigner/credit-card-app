<?php

namespace Tests\Unit;

use App\Models\Benefit;
use App\Models\Card;
use App\Services\RecommendationService;
use App\Services\UtilizationService;
use App\Services\WaiverService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RecommendationServiceTest extends TestCase
{
    private RecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecommendationService(new UtilizationService(), new WaiverService());
    }

    private function cardWith(array $attrs, array $benefits = []): Card
    {
        $card = new Card(array_merge([
            'total_limit' => 100000,
            'current_outstanding' => 0,
            'due_day' => 25,
            'waiver_spend_required' => 0,
            'waiver_spend_completed' => 0,
            'card_year_start_month' => 1,
            'best_categories' => [],
            'reward_rate_general' => 0,
        ], $attrs));
        $card->id = $attrs['id'] ?? 1;

        $card->setRelation('benefits', new Collection(array_map(
            fn (array $b) => new Benefit($b),
            $benefits,
        )));

        return $card;
    }

    public function test_score_breakdown_with_no_waiver_no_category_no_benefits(): void
    {
        $now = Carbon::create(2026, 6, 15);
        $card = $this->cardWith(['current_outstanding' => 0, 'due_day' => 25]);

        $result = $this->service->scoreCard($card, null, $now);

        $this->assertSame(0.0, $result['breakdown']['waiver_urgency_score']);
        $this->assertSame(0.0, $result['breakdown']['reward_category_score']);
        $this->assertSame(0.0, $result['breakdown']['unused_benefit_score']);
        $this->assertSame(0.0, $result['breakdown']['utilization_penalty']);
        $this->assertSame(0.0, $result['breakdown']['due_date_risk_penalty']);
        $this->assertSame(0.0, $result['total_score']);
    }

    public function test_category_match_scores_thirty(): void
    {
        $now = Carbon::create(2026, 6, 15);
        $card = $this->cardWith(['best_categories' => ['fuel', 'grocery'], 'due_day' => 25]);

        $result = $this->service->scoreCard($card, 'fuel', $now);

        $this->assertSame(30.0, $result['breakdown']['reward_category_score']);
    }

    public function test_category_no_match_falls_back_to_general_reward_rate(): void
    {
        $now = Carbon::create(2026, 6, 15);
        $card = $this->cardWith(['best_categories' => ['dining'], 'reward_rate_general' => 2, 'due_day' => 25]);

        $result = $this->service->scoreCard($card, 'fuel', $now);

        // min(15, 2 * 3) = 6
        $this->assertSame(6.0, $result['breakdown']['reward_category_score']);
    }

    public function test_category_fallback_caps_at_fifteen(): void
    {
        $now = Carbon::create(2026, 6, 15);
        $card = $this->cardWith(['best_categories' => [], 'reward_rate_general' => 10, 'due_day' => 25]);

        $result = $this->service->scoreCard($card, 'fuel', $now);

        $this->assertSame(15.0, $result['breakdown']['reward_category_score']);
    }

    public function test_unused_benefit_score_counts_expiring_soon_higher(): void
    {
        $now = Carbon::create(2026, 6, 15);
        $card = $this->cardWith(['due_day' => 25], [
            ['total_allowed' => 4, 'used_count' => 0, 'expiry_date' => null], // not expiring: +4
            ['total_allowed' => 4, 'used_count' => 0, 'expiry_date' => $now->copy()->addDays(10)->toDateString()], // expiring soon: +8
            ['total_allowed' => 4, 'used_count' => 4, 'expiry_date' => null], // fully used: +0
        ]);

        $result = $this->service->scoreCard($card, null, $now);

        $this->assertSame(12.0, $result['breakdown']['unused_benefit_score']);
    }

    public function test_unused_benefit_score_caps_at_twenty(): void
    {
        $now = Carbon::create(2026, 6, 15);
        $benefits = array_fill(0, 5, ['total_allowed' => 4, 'used_count' => 0, 'expiry_date' => $now->copy()->addDays(5)->toDateString()]);
        $card = $this->cardWith(['due_day' => 25], $benefits);

        $result = $this->service->scoreCard($card, null, $now);

        $this->assertSame(20.0, $result['breakdown']['unused_benefit_score']);
    }

    public function test_utilization_penalty_matches_bands(): void
    {
        $now = Carbon::create(2026, 6, 15);

        $good = $this->cardWith(['total_limit' => 100000, 'current_outstanding' => 10000, 'due_day' => 25]);
        $caution = $this->cardWith(['total_limit' => 100000, 'current_outstanding' => 35000, 'due_day' => 25]);
        $avoid = $this->cardWith(['total_limit' => 100000, 'current_outstanding' => 60000, 'due_day' => 25]);
        $urgent = $this->cardWith(['total_limit' => 100000, 'current_outstanding' => 90000, 'due_day' => 25]);

        $this->assertSame(0.0, $this->service->scoreCard($good, null, $now)['breakdown']['utilization_penalty']);
        $this->assertSame(10.0, $this->service->scoreCard($caution, null, $now)['breakdown']['utilization_penalty']);
        $this->assertSame(25.0, $this->service->scoreCard($avoid, null, $now)['breakdown']['utilization_penalty']);
        $this->assertSame(50.0, $this->service->scoreCard($urgent, null, $now)['breakdown']['utilization_penalty']);
    }

    public function test_due_date_risk_penalty_matches_thresholds(): void
    {
        $now = Carbon::create(2026, 6, 15);

        $dueSoon = $this->cardWith(['due_day' => 17]); // 2 days away => 20
        $dueWeek = $this->cardWith(['due_day' => 20]); // 5 days away => 10
        $dueFar = $this->cardWith(['due_day' => 28]); // 13 days away => 0

        $this->assertSame(20.0, $this->service->scoreCard($dueSoon, null, $now)['breakdown']['due_date_risk_penalty']);
        $this->assertSame(10.0, $this->service->scoreCard($dueWeek, null, $now)['breakdown']['due_date_risk_penalty']);
        $this->assertSame(0.0, $this->service->scoreCard($dueFar, null, $now)['breakdown']['due_date_risk_penalty']);
    }

    public function test_total_score_sums_components_correctly(): void
    {
        $now = Carbon::create(2026, 6, 15);
        $card = $this->cardWith([
            'best_categories' => ['fuel'],
            'total_limit' => 100000,
            'current_outstanding' => 10000, // good band, penalty 0
            'due_day' => 28, // far, penalty 0
            'waiver_spend_required' => 0,
        ], [
            ['total_allowed' => 4, 'used_count' => 0, 'expiry_date' => null], // +4
        ]);

        $result = $this->service->scoreCard($card, 'fuel', $now);

        // 0 (waiver) + 30 (category match) + 4 (benefit) - 0 (util) - 0 (due) = 34
        $this->assertSame(34.0, $result['total_score']);
    }

    public function test_recommend_ranks_higher_score_first(): void
    {
        $now = Carbon::create(2026, 6, 15);
        $highUtilCard = $this->cardWith(['id' => 1, 'total_limit' => 100000, 'current_outstanding' => 90000, 'due_day' => 28]);
        $lowUtilCard = $this->cardWith(['id' => 2, 'total_limit' => 100000, 'current_outstanding' => 5000, 'due_day' => 28]);

        $ranked = $this->service->recommend(new Collection([$highUtilCard, $lowUtilCard]), null, $now);

        $this->assertSame(2, $ranked[0]['card_id']);
        $this->assertSame(1, $ranked[1]['card_id']);
    }

    public function test_multi_category_score_takes_best_match_not_sum(): void
    {
        $now = Carbon::create(2026, 6, 15);
        $card = $this->cardWith(['best_categories' => ['grocery'], 'due_day' => 25]);

        $result = $this->service->scoreCard($card, ['fuel', 'grocery', 'medicines'], $now);

        // matches only 'grocery' among the three -> best match score (30), not a sum across categories.
        $this->assertSame(30.0, $result['breakdown']['reward_category_score']);
    }

    public function test_multi_category_score_falls_back_to_general_rate_when_none_match(): void
    {
        $now = Carbon::create(2026, 6, 15);
        $card = $this->cardWith(['best_categories' => ['travel'], 'reward_rate_general' => 2, 'due_day' => 25]);

        $result = $this->service->scoreCard($card, ['fuel', 'online_food'], $now);

        $this->assertSame(6.0, $result['breakdown']['reward_category_score']);
    }

    public function test_monthly_plan_returns_top_n_cards_blending_waiver_and_categories(): void
    {
        $now = Carbon::create(2026, 6, 15);

        $waiverUrgent = $this->cardWith([
            'id' => 1, 'due_day' => 28,
            'waiver_spend_required' => 100000, 'waiver_spend_completed' => 0, 'card_year_start_month' => 7,
        ]);
        $categoryMatch = $this->cardWith(['id' => 2, 'due_day' => 28, 'best_categories' => ['grocery']]);
        $irrelevant = $this->cardWith(['id' => 3, 'due_day' => 28]);

        $plan = $this->service->monthlyPlan(
            new Collection([$irrelevant, $waiverUrgent, $categoryMatch]),
            ['grocery', 'medicines'],
            $now,
            2
        );

        $this->assertCount(2, $plan);
        $planIds = array_column($plan, 'card_id');
        $this->assertContains(2, $planIds);
        $this->assertNotContains(3, $planIds);
    }
}
