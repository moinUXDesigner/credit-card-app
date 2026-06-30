<?php

namespace Tests\Unit;

use App\Models\Card;
use App\Services\UtilizationService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UtilizationServiceTest extends TestCase
{
    private UtilizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UtilizationService();
    }

    private function cardWith(float $limit, float $outstanding): Card
    {
        return new Card(['total_limit' => $limit, 'current_outstanding' => $outstanding]);
    }

    public function test_card_utilization_calculates_percentage(): void
    {
        $card = $this->cardWith(100000, 25000);

        $this->assertEqualsWithDelta(25.0, $this->service->cardUtilization($card), 0.001);
    }

    public function test_card_utilization_guards_against_zero_limit(): void
    {
        $card = $this->cardWith(0, 5000);

        $this->assertEqualsWithDelta(0.0, $this->service->cardUtilization($card), 0.001);
    }

    public function test_overall_utilization_sums_across_cards(): void
    {
        $cards = new Collection([
            $this->cardWith(100000, 50000),
            $this->cardWith(200000, 50000),
        ]);

        // total outstanding 100000 / total limit 300000 = 33.33%
        $this->assertEqualsWithDelta(33.33, $this->service->overallUtilization($cards), 0.01);
    }

    public function test_overall_utilization_guards_against_zero_total_limit(): void
    {
        $cards = new Collection([$this->cardWith(0, 0)]);

        $this->assertEqualsWithDelta(0.0, $this->service->overallUtilization($cards), 0.001);
    }

    #[DataProvider('bandBoundaryProvider')]
    public function test_band_boundaries(float $utilizationPct, string $expectedBand): void
    {
        $this->assertSame($expectedBand, $this->service->band($utilizationPct));
    }

    public static function bandBoundaryProvider(): array
    {
        return [
            'well under 30' => [0.0, 'good'],
            'just under 30' => [29.99, 'good'],
            'exactly 30' => [30.0, 'caution'],
            'just under 50' => [49.99, 'caution'],
            'exactly 50' => [50.0, 'avoid_further_use'],
            'just under 75' => [74.99, 'avoid_further_use'],
            'exactly 75' => [75.0, 'urgent_repayment'],
            'over 75' => [90.0, 'urgent_repayment'],
        ];
    }

    public function test_band_message_never_mentions_cibil_or_score_improvement(): void
    {
        foreach (['good', 'caution', 'avoid_further_use', 'urgent_repayment'] as $band) {
            $message = $this->service->bandMessage($band);
            $this->assertStringNotContainsStringIgnoringCase('cibil', $message);
            $this->assertStringNotContainsStringIgnoringCase('improve', $message);
        }
    }
}
