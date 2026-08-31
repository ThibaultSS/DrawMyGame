<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DrawnLevelController extends Controller
{
    private const PALETTE = [
        'platform' => '#000000',
        'goal' => '#00aa00',
        'player' => '#0000ff',
        'hazard' => '#ff0000',
    ];

    public function __invoke(): Response
    {
        return Inertia::render('Draw', [
            'palette' => self::PALETTE,
        ]);
    }
}
