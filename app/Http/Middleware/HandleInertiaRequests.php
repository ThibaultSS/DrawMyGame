<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Only the fields the interface actually renders are shared, never the whole
     * user model: everything in here is serialised into the page and is readable
     * by anyone who views the source.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array{auth: array{user: array{id: int, username: string, initials: string}|null}}
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'username' => $user->username,
                    'initials' => mb_strtoupper(mb_substr($user->username, 0, 2)),
                ] : null,
            ],
        ];
    }
}
