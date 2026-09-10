<?php

namespace App\Filament\Resources\AreaPreparacions\Pages;

use App\Filament\Resources\AreaPreparacions\AreaPreparacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAreaPreparacions extends ListRecords
{
    protected static string $resource = AreaPreparacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
