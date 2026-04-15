<?php

namespace App\Filament\Resources\CurriculumResource\Pages;

use App\Filament\Resources\CurriculumResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCurriculum extends CreateRecord
{
    protected static string $resource = CurriculumResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['crp_id'] = auth()->user()->crp_id;
        return $data;
    }
}
