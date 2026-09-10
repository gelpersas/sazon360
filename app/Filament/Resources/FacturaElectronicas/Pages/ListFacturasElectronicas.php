<?php

namespace App\Filament\Resources\FacturaElectronicas\Pages;

use App\Filament\Resources\FacturaElectronicas\FacturaElectronicaResource;
use Filament\Resources\Pages\ListRecords;

class ListFacturasElectronicas extends ListRecords
{
    protected static string $resource = FacturaElectronicaResource::class;
}
