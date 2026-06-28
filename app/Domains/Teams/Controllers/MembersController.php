<?php

namespace App\Domains\Teams\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Enums\TeamPermission;

class MembersController extends Controller
{
    public function index(Request $request)
    {
        $team = $request->user()->currentTeam;
        
        $team->load('memberships.user', 'invitations');

        // We can group permissions or just send them as a flat array
        $availablePermissions = array_map(function ($case) {
            return [
                'value' => $case->value,
                'name' => $case->name,
            ];
        }, TeamPermission::cases());

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
            'role_permissions' => empty($team->role_permissions) ? [
                'admin' => array_map(fn($p) => $p->value, \App\Enums\TeamRole::Admin->permissions()),
                'member' => array_map(fn($p) => $p->value, \App\Enums\TeamRole::Member->permissions()),
            ] : collect($team->role_permissions)->toArray(),
            'availablePermissions' => $availablePermissions,
        ]);
    }
}
