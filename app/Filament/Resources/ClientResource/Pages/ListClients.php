<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $crpId = auth()->user()->crp_id;

        $allCount = Client::where('crp_id', $crpId)->count();

        $missingCount = Client::where('crp_id', $crpId)
            ->where(function (Builder $q) {
                $q->whereNull('proof_of_disability_received_at')
                  ->orWhereNull('iep_received_at')
                  ->orWhereNull('consent_form_signed_at');
            })->count();

        $completeCount = $allCount - $missingCount;

        return [
            'all' => Tab::make('All Clients')
                ->badge($allCount),

            'missing' => Tab::make('Missing Documents')
                ->badge($missingCount)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function (Builder $q) {
                    $q->whereNull('proof_of_disability_received_at')
                      ->orWhereNull('iep_received_at')
                      ->orWhereNull('consent_form_signed_at');
                })),

            'complete' => Tab::make('Documents Complete')
                ->badge($completeCount)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('proof_of_disability_received_at')
                    ->whereNotNull('iep_received_at')
                    ->whereNotNull('consent_form_signed_at')
                ),
        ];
    }
}
