<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriverApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->status !== UserStatus::Approved) {
            throw ApiException::driverNotApproved();
        }

        return $next($request);
    }
}
