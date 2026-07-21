<?php

use App\Domains\Requests\Models\Request;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('requests.{requestId}', function ($user, $requestId) {
    $request = Request::find($requestId);
    if (! $request) {
        return false;
    }

    return $user->teams()->where('teams.id', $request->collection->team_id)->exists();
});
