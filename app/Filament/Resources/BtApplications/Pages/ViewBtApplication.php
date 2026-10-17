<?php

namespace App\Filament\Resources\BtApplications\Pages;

use App\Filament\Resources\BtApplications\BtApplicationResource;
use App\Filament\Resources\BtApplications\ReviewActions;
use Filament\Resources\Pages\ViewRecord;

class ViewBtApplication extends ViewRecord
{
    protected static string $resource = BtApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return ReviewActions::make();
    }
}
