<?php

namespace App\Domains\Teams\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MembersController extends Controller
{
    public function index(Request $request)
    {
        $team = $request->user()->currentTeam;

        $team->load('memberships.user', 'invitations');

        return Inertia::render('teams/Members', [
            'members' => $team->memberships->map(function ($membership) {
                return [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role,
                ];
            }),
            'invitations' => $team->invitations,
        ]);
    }
}
