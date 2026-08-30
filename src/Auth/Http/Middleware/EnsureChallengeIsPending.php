<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PrimeVueKit\Auth\ChallengeSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege las pantallas de reto: sin reto pendiente se vuelve al login.
 */
class EnsureChallengeIsPending
{
    public function __construct(private readonly ChallengeSession $challenge) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->challenge->pending($request)) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
