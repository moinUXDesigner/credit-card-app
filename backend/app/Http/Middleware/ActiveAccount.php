<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class ActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(User::whereKey($request->user()?->id)->whereNotNull('suspended_at')->exists(), 403, 'This account is suspended.');

        return $next($request);
    }
}
