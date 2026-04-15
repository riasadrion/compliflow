<?php

namespace App\Filament\Resources\WbleEmployerResource\Pages;

use App\Filament\Resources\WbleEmployerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWbleEmployer extends EditRecord
{
    protected static string $resource = WbleEmployerResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
