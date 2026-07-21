<?php

namespace App\Http\Middleware;

use App\Enums\TeamPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $team = $request->route('current_team') ?? $request->route('team') ?? $user?->currentTeam;

        abort_if(! $user || ! $team || ! $user->belongsToTeam($team), 403);

        $teamPermission = TeamPermission::tryFrom($permission);

        abort_if(
            $teamPermission === null || ! $user->hasTeamPermission($team, $teamPermission),
            403,
            'You do not have permission to perform this action.'
        );

        if ($request->route('current_team') && ! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }

        return $next($request);
    }
}
