<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\EnforcesRoles;
use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use App\Services\CryptographicAuditService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    use EnforcesRoles;

    protected static ?string $model = Client::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Clients';
    protected static string|\UnitEnum|null $navigationGroup = 'Case Management';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ! $user->isSuperAdmin() && $user->crp_id !== null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Personal Information')
                ->description('PHI — encrypted at rest')
                ->icon('heroicon-o-lock-closed')
                ->columns(2)
                ->schema([
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('last_name')
                        ->required()
                        ->maxLength(100),

                    DatePicker::make('dob')
                        ->label('Date of Birth')
                        ->required()
                        ->before('today')
                        ->displayFormat('M j, Y'),

                    TextInput::make('ssn_last_four')
                        ->label('SSN Last 4')
                        ->maxLength(4)
                        ->minLength(4)
                        ->numeric(),
                ]),

            Section::make('Contact')
                ->columns(2)
                ->schema([
                    TextInput::make('address')
                        ->columnSpanFull(),

                    TextInput::make('phone')
                        ->tel(),

                    TextInput::make('email')
                        ->email(),
                ]),

            Section::make('Eligibility')
                ->columns(2)
                ->schema([
                    TextInput::make('external_id')
                        ->label('External ID')
                        ->helperText('The client\'s ID in your existing system (e.g. case management software, spreadsheet, or state database). Used to match records during CSV imports and prevent duplicates. Leave blank if not applicable.'),

                    Select::make('eligibility_status')
                        ->options([
                            'pending'              => 'Pending',
                            'potentially_eligible' => 'Potentially Eligible',
                            'eligible'             => 'Eligible',
                            'ineligible'           => 'Ineligible',
                        ])
                        ->required()
                        ->default('pending'),
                ]),

            Section::make('Document Tracking')
                ->description('File uploads available in Week 3 (S3). Record receipt dates now.')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('proof_of_disability_received_at')
                        ->label('Proof of Disability Received'),

                    DateTimePicker::make('iep_received_at')
                        ->label('IEP Received'),

                    DateTimePicker::make('consent_form_signed_at')
                        ->label('Consent Form Signed'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('full_name')
                    ->label('Name')
                    ->getStateUsing(fn (Client $record): string =>
                        $record->first_name . ' ' . $record->last_name
                    )
                    ->searchable(query: function ($query, string $search) {
                        $query->whereRaw("LOWER(first_name) LIKE ?", ["%{$search}%"])
                              ->orWhereRaw("LOWER(last_name) LIKE ?", ["%{$search}%"]);
                    })
                    ->sortable(query: function ($query, string $direction) {
                        $query->orderBy('last_name', $direction);
                    }),

                TextColumn::make('eligibility_status')
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

                TextColumn::make('documents')
                    ->label('Documents')
                    ->getStateUsing(function (Client $record): string {
                        $total   = 3;
                        $received = collect([
                            $record->proof_of_disability_received_at,
                            $record->iep_received_at,
                            $record->consent_form_signed_at,
                        ])->filter()->count();

                        return "{$received}/{$total}";
                    })
                    ->badge()
                    ->color(function (Client $record): string {
                        $received = collect([
                            $record->proof_of_disability_received_at,
                            $record->iep_received_at,
                            $record->consent_form_signed_at,
                        ])->filter()->count();

                        return match (true) {
                            $received === 3 => 'success',
                            $received > 0   => 'warning',
                            default         => 'danger',
                        };
                    }),

                TextColumn::make('authorizations_count')
                    ->label('Auths')
                    ->counts('authorizations')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('eligibility_status')
                    ->options([
                        'pending'              => 'Pending',
                        'potentially_eligible' => 'Potentially Eligible',
                        'eligible'             => 'Eligible',
                        'ineligible'           => 'Ineligible',
                    ]),

                Filter::make('missing_documents')
                    ->label('Missing Documents')
                    ->query(fn ($query) => $query->where(function ($q) {
                        $q->whereNull('proof_of_disability_received_at')
                          ->orWhereNull('iep_received_at')
                          ->orWhereNull('consent_form_signed_at');
                    }))
                    ->toggle(),

                Filter::make('documents_complete')
                    ->label('Documents Complete')
                    ->query(fn ($query) => $query
                        ->whereNotNull('proof_of_disability_received_at')
                        ->whereNotNull('iep_received_at')
                        ->whereNotNull('consent_form_signed_at')
                    )
                    ->toggle(),
            ])
            ->actions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_name', 'asc');
    }

    public static function getRelationManagers(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit'   => Pages\EditClient::route('/{record}/edit'),
            'view'   => Pages\ViewClient::route('/{record}'),
        ];
    }
}
