<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks suspended accounts from protected features. GET /users/me and logout stay outside this group. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->status === UserStatus::Suspended) {
            throw ApiException::accountSuspended();
        }

        return $next($request);
    }
}
