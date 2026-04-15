<?php

namespace App\Filament\Resources\CrpUserResource\Pages;

use App\Filament\Resources\CrpUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCrpUser extends EditRecord
{
    protected static string $resource = CrpUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
