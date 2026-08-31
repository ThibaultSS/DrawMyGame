<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class Controller
{
    protected const PER_PAGE = 12;

    /**
     * @param  LengthAwarePaginator<int, mixed>  $items
     */
    protected function pageOutOfRange(LengthAwarePaginator $items, string $routeName): ?RedirectResponse
    {
        if ($items->isNotEmpty() || $items->currentPage() <= $items->lastPage()) {
            return null;
        }

        return redirect()->route($routeName, ['page' => $items->lastPage()]);
    }
}
