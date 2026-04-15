<?php

namespace App\Filament\Resources\AuthorizationResource\Pages;

use App\Filament\Resources\AuthorizationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAuthorizations extends ListRecords
{
    protected static string $resource = AuthorizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
