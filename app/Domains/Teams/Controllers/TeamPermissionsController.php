<?php

namespace App\Domains\Teams\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Enums\TeamPermission;

class TeamPermissionsController extends Controller
{
    /**
     * Update the team's role permissions.
     */
    public function update(Request $request, \App\Domains\Teams\Models\Team $team)
    {

        if (! $request->user()->ownsTeam($team)) {
            abort(403, 'Only team owners can update role permissions.');
        }

        $validated = $request->validate([
            'role_permissions' => 'required|array',
        ]);

        // Validate that provided permissions are actual Enum cases
        $validPermissions = array_column(TeamPermission::cases(), 'value');
        
        $rolePermissions = [];
        foreach ($validated['role_permissions'] as $role => $permissions) {
            $rolePermissions[$role] = array_values(array_filter($permissions, function ($perm) use ($validPermissions) {
                return in_array($perm, $validPermissions);
            }));
        }

        $team->update([
            'role_permissions' => $rolePermissions,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Team role permissions updated successfully.',
        ]);

        return back();
    }
}
