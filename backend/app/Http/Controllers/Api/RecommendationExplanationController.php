<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Services\RecommendationExplanationService;
use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecommendationExplanationController extends Controller
{
    public function __invoke(Request $r)
    {
        $d = $r->validate(['category' => ['nullable', Rule::in(Card::CATEGORIES)]]);
        $cards = $r->user()->accessibleCards()->with('benefits')->where('is_active', true)->get();
        $rank = app(RecommendationService::class)->recommend($cards, $d['category'] ?? null);

        return response()->json(app(RecommendationExplanationService::class)->explain($rank));
    }
}
