<?php

namespace App\Http\Responses\Concerns;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam($request, string $redirect): string
    {
        $team = $this->currentTeam($request);

        return "{$redirect}";
    }

    protected function currentTeam($request)
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->personalTeam();

        if (! $team) {
            abort(403);
        }

        return $team;
    }
}
