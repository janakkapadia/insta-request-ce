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
        abort(403, 'Custom ACL permissions are not available in Community Edition.');
    }
}
