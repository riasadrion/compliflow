<?php

namespace App\Filament\Resources\WblePayrollResource\Pages;

use App\Filament\Resources\WblePayrollResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWblePayrolls extends ListRecords
{
    protected static string $resource = WblePayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
