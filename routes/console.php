<?php

use App\Domains\Collections\Models\Collection;
use App\Domains\Collections\Models\CollectionFolder;
use App\Domains\History\Models\RequestHistory;
use App\Domains\Requests\Models\Request;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', [
    '--model' => [
        RequestHistory::class,
        Collection::class,
        CollectionFolder::class,
        Request::class,
    ],
])->hourly()->withoutOverlapping();
