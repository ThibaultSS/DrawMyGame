<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DrawnLevelController extends Controller
{
    /**
     * The colours the Draw page *starts* with. They are the page's defaults
     * rather than its palette: each one can be changed, and whatever is chosen
     * is what gets posted to /start-game, which is why a drawn level still
     * needs no eyedropper.
     *
     * These four are far apart in RGB on purpose — the detector matches within
     * a tolerance of 70, so near neighbours bleed into each other. The page
     * holds anything chosen instead of them to the same rule.
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
     *
     * The colours go out as a starting point and come back through
     * /start-game, which validates any #rrggbb — so choosing your own needed
     * nothing here beyond saying that these are only defaults.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Draw', [
            'palette' => self::PALETTE,
        ]);
    }
}
