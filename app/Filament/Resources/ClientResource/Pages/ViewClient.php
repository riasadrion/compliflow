<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use App\Services\CryptographicAuditService;
use Filament\Actions;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Personal Information')
                ->icon('heroicon-o-lock-closed')
                ->columns(2)
                ->schema([
                    TextEntry::make('first_name')->label('First Name'),
                    TextEntry::make('last_name')->label('Last Name'),
                    TextEntry::make('dob')->label('Date of Birth'),
                    TextEntry::make('ssn_last_four')->label('SSN Last 4'),
                ]),

            Section::make('Contact')
                ->columns(2)
                ->schema([
                    TextEntry::make('address')->columnSpanFull(),
                    TextEntry::make('phone'),
                    TextEntry::make('email'),
                ]),

            Section::make('Eligibility')
                ->columns(2)
                ->schema([
                    TextEntry::make('external_id')->label('External ID'),
                    TextEntry::make('eligibility_status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'eligible'             => 'success',
                            'potentially_eligible' => 'warning',
                            'pending'              => 'gray',
                            'ineligible'           => 'danger',
                            default                => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'potentially_eligible' => 'Potentially Eligible',
                            default                => ucfirst($state),
                        }),
                ]),

            Section::make('Document Status')
                ->columns(3)
                ->schema([
                    IconEntry::make('has_proof_of_disability')
                        ->label('Proof of Disability')
                        ->boolean()
                        ->trueIcon('heroicon-o-check-circle')
                        ->falseIcon('heroicon-o-x-circle')
                        ->trueColor('success')
                        ->falseColor('danger')
                        ->getStateUsing(fn (Client $record): bool => (bool) $record->proof_of_disability_received_at),

                    IconEntry::make('has_iep')
                        ->label('IEP')
                        ->boolean()
                        ->trueIcon('heroicon-o-check-circle')
                        ->falseIcon('heroicon-o-x-circle')
                        ->trueColor('success')
                        ->falseColor('danger')
                        ->getStateUsing(fn (Client $record): bool => (bool) $record->iep_received_at),

                    IconEntry::make('has_consent_form')
                        ->label('Consent Form')
                        ->boolean()
                        ->trueIcon('heroicon-o-check-circle')
                        ->falseIcon('heroicon-o-x-circle')
                        ->trueColor('success')
                        ->falseColor('danger')
                        ->getStateUsing(fn (Client $record): bool => (bool) $record->consent_form_signed_at),

                    TextEntry::make('proof_of_disability_received_at')
                        ->label('Received')
                        ->dateTime('M j, Y')
                        ->placeholder('Not received'),

                    TextEntry::make('iep_received_at')
                        ->label('Received')
                        ->dateTime('M j, Y')
                        ->placeholder('Not received'),

                    TextEntry::make('consent_form_signed_at')
                        ->label('Signed')
                        ->dateTime('M j, Y')
                        ->placeholder('Not signed'),
                ]),
        ]);
    }

    protected function afterView(): void
    {
        $user = auth()->user();
        app(CryptographicAuditService::class)->log(
            $user->crp_id,
            $user->id,
            'client_viewed',
            'client',
            $this->record->id,
            ['classification' => 'compliance'],
        );
    }
}
