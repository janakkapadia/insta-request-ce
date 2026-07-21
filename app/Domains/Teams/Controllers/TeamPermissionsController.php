<?php

namespace App\Domains\Teams\Controllers;

use App\Domains\Teams\Models\Team;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TeamPermissionsController extends Controller
{
    /**
     * Update the team's role permissions.
     */
    public function update(Request $request, Team $team)
    {
        abort(403, 'Custom ACL permissions are not available in Community Edition.');
    }
}
