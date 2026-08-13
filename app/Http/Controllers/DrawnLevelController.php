<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DrawnLevelController extends Controller
{
    /**
     * The colours the Draw page paints with, and therefore the colours the
     * engine is told to look for. The page renders its buttons from this array
     * and posts these same values to /start-game, which is why a drawn level
     * needs no eyedropper.
     *
     * They are far apart in RGB on purpose: the detector matches colours with
     * a tolerance of 70, so near neighbours would bleed into each other.
     */
    private const PALETTE = [
        'platform' => '#000000',
        'goal' => '#00aa00',
        'player' => '#0000ff',
        'hazard' => '#ff0000',
    ];

    /**
     * Only the page. The drawing itself never reaches this server unless it is
     * saved: the canvas becomes a blob the browser keeps and plays from.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Draw', [
            'palette' => self::PALETTE,
        ]);
    }
}
