<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\EnforcesRoles;
use App\Filament\Resources\AuthorizationResource\Pages;
use App\Models\Authorization;
use App\Models\Client;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Validation\Rule;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuthorizationResource extends Resource
{
    use EnforcesRoles;

    protected static ?string $model = Authorization::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Authorizations';
    protected static string|\UnitEnum|null $navigationGroup = 'Case Management';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ! $user->isSuperAdmin() && $user->crp_id !== null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Client & Authorization')
                ->columns(2)
                ->schema([
                    Select::make('client_id')
                        ->label('Client')
                        ->options(fn () =>
                            Client::where('crp_id', auth()->user()->crp_id)
                                ->get()
                                ->mapWithKeys(fn (Client $c) => [
                                    $c->id => $c->last_name . ', ' . $c->first_name,
                                ])
                        )
                        ->searchable()
                        ->required(),

                    TextInput::make('authorization_number')
                        ->label('Authorization #')
                        ->required()
                        ->maxLength(100)
                        ->rules(fn ($record) => [
                            Rule::unique('authorizations', 'authorization_number')
                                ->where('crp_id', auth()->user()->crp_id)
                                ->ignore($record?->id),
                        ])
                        ->validationMessages([
                            'unique' => 'This authorization number already exists in your organization. Use a different number or edit the existing authorization.',
                        ]),

                    Select::make('service_code')
                        ->label('Service Code')
                        ->options([
                            '127X' => '127X — Individual Pre-ETS',
                            '963X' => '963X — Pre-ETS',
                            '964X' => '964X — WBLE',
                            '122X' => '122X — Counseling & Guidance',
                        ])
                        ->required(),

                    TextInput::make('service_type')
                        ->label('Service Type')
                        ->required()
                        ->maxLength(100),
                ]),

            Section::make('Dates & Units')
                ->columns(2)
                ->schema([
                    DatePicker::make('start_date')
                        ->required(),

                    DatePicker::make('end_date')
                        ->required()
                        ->after('start_date'),

                    TextInput::make('authorized_units')
                        ->label('Authorized Units')
                        ->numeric()
                        ->required()
                        ->minValue(1),

                    TextInput::make('units_used')
                        ->label('Units Used')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->rules(fn ($get) => ['lte:' . ($get('authorized_units') ?: 99999)])
                        ->validationMessages([
                            'lte' => 'Units used cannot exceed authorized units.',
                        ]),
                ]),

            Section::make('VRC & Office')
                ->columns(2)
                ->schema([
                    TextInput::make('vrc_name')
                        ->label('VRC Name'),

                    TextInput::make('vrc_email')
                        ->label('VRC Email')
                        ->email(),

                    TextInput::make('district_office')
                        ->label('District Office'),

                    Select::make('status')
                        ->options([
                            'pending'    => 'Pending',
                            'active'     => 'Active',
                            'expired'    => 'Expired',
                            'exhausted'  => 'Exhausted',
                            'terminated' => 'Terminated',
                        ])
                        ->required()
                        ->default('pending'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.last_name')
                    ->label('Client')
                    ->getStateUsing(fn (Authorization $record): string =>
                        $record->client->last_name . ', ' . $record->client->first_name
                    )
                    ->searchable(query: fn ($query, string $search) =>
                        $query->whereHas('client', fn ($q) =>
                            $q->whereRaw("LOWER(last_name) LIKE ?", ["%{$search}%"])
                              ->orWhereRaw("LOWER(first_name) LIKE ?", ["%{$search}%"])
                        )
                    )
                    ->sortable(query: fn ($query, string $direction) =>
                        $query->join('clients', 'authorizations.client_id', '=', 'clients.id')
                              ->orderBy('clients.last_name', $direction)
                    ),

                TextColumn::make('authorization_number')
                    ->label('Auth #')
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('service_code')
                    ->label('Code')
                    ->badge()
                    ->color('info'),

                TextColumn::make('units_progress')
                    ->label('Units')
                    ->getStateUsing(fn (Authorization $record): string =>
                        $record->units_used . ' / ' . $record->authorized_units
                        . ' (' . $record->units_percent_used . '%)'
                    )
                    ->badge()
                    ->color(fn (Authorization $record): string => match (true) {
                        $record->units_percent_used >= 90 => 'danger',
                        $record->units_percent_used >= 70 => 'warning',
                        default                           => 'success',
                    }),

                TextColumn::make('end_date')
                    ->label('Expires')
                    ->date('M j, Y')
                    ->sortable()
                    ->color(fn (Authorization $record): string => match (true) {
                        $record->end_date->isPast()                       => 'danger',
                        $record->end_date->diffInDays(now()) <= 30        => 'warning',
                        default                                            => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'     => 'success',
                        'pending'    => 'gray',
                        'expired'    => 'danger',
                        'exhausted'  => 'danger',
                        'terminated' => 'danger',
                        default      => 'gray',
                    }),

                TextColumn::make('vrc_name')
                    ->label('VRC')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'    => 'Pending',
                        'active'     => 'Active',
                        'expired'    => 'Expired',
                        'exhausted'  => 'Exhausted',
                        'terminated' => 'Terminated',
                    ]),

                SelectFilter::make('service_code')
                    ->label('Service Code')
                    ->options(
                        Authorization::where('crp_id', auth()->user()?->crp_id)
                            ->distinct()
                            ->pluck('service_code', 'service_code')
                            ->toArray()
                    ),
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
            ->defaultSort('end_date', 'asc');
    }

    public static function getRelationManagers(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAuthorizations::route('/'),
            'create' => Pages\CreateAuthorization::route('/create'),
            'edit'   => Pages\EditAuthorization::route('/{record}/edit'),
            'view'   => Pages\ViewAuthorization::route('/{record}'),
        ];
    }
}
