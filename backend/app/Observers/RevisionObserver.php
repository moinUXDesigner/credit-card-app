<?php

namespace App\Observers;

use App\Services\SyncEventService;

class RevisionObserver
{
    public function updating($model): void
    {
        $model->revision = ((int) $model->getOriginal('revision')) + 1;
    }

    public function created($model): void
    {
        app(SyncEventService::class)->record($model, 'upsert');
    }

    public function updated($model): void
    {
        app(SyncEventService::class)->record($model, 'upsert');
    }

    public function deleting($model): void
    {
        app(SyncEventService::class)->record($model, 'delete');
    }
}
