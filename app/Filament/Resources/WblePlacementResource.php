<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\EnforcesRoles;
use App\Filament\Resources\WblePlacementResource\Pages;
use App\Models\Client;
use App\Models\WbleEmployer;
use App\Models\WblePlacement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WblePlacementResource extends Resource
{
    use EnforcesRoles;

    protected static ?string $model = WblePlacement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Placements';
    protected static string|\UnitEnum|null $navigationGroup = 'WBLE';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ! $user->isSuperAdmin() && $user->crp_id !== null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Placement Details')
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

                    Select::make('wble_employer_id')
                        ->label('Employer')
                        ->options(fn () =>
                            WbleEmployer::where('crp_id', auth()->user()->crp_id)
                                ->where('is_active', true)
                                ->pluck('employer_name', 'id')
                        )
                        ->searchable()
                        ->required(),

                    \Filament\Forms\Components\TextInput::make('job_title')
                        ->label('Job Title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('job_duties')
                        ->label('Job Duties')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Dates & Status')
                ->columns(2)
                ->schema([
                    DatePicker::make('planned_start_date')
                        ->label('Planned Start')
                        ->required(),

                    DatePicker::make('actual_start_date')
                        ->label('Actual Start'),

                    DatePicker::make('end_date')
                        ->label('End Date'),

                    Select::make('status')
                        ->options([
                            'pending'    => 'Pending',
                            'active'     => 'Active',
                            'completed'  => 'Completed',
                            'terminated' => 'Terminated',
                        ])
                        ->default('pending')
                        ->required(),

                    DateTimePicker::make('district_notice_sent_at')
                        ->label('District Notice Sent'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.last_name')
                    ->label('Client')
                    ->getStateUsing(fn (WblePlacement $record): string =>
                        $record->client->last_name . ', ' . $record->client->first_name
                    )
                    ->searchable(query: fn ($query, string $search) =>
                        $query->whereHas('client', fn ($q) =>
                            $q->whereRaw("LOWER(last_name) LIKE ?", ["%{$search}%"])
                              ->orWhereRaw("LOWER(first_name) LIKE ?", ["%{$search}%"])
                        )
                    )
                    ->sortable(query: fn ($query, string $direction) =>
                        $query->join('clients', 'wble_placements.client_id', '=', 'clients.id')
                              ->orderBy('clients.last_name', $direction)
                    ),

                TextColumn::make('employer.employer_name')
                    ->label('Employer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('job_title')
                    ->label('Job Title')
                    ->searchable(),

                TextColumn::make('planned_start_date')
                    ->label('Start')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'     => 'success',
                        'pending'    => 'gray',
                        'completed'  => 'info',
                        'terminated' => 'danger',
                        default      => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'    => 'Pending',
                        'active'     => 'Active',
                        'completed'  => 'Completed',
                        'terminated' => 'Terminated',
                    ]),
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
            ->defaultSort('planned_start_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWblePlacements::route('/'),
            'create' => Pages\CreateWblePlacement::route('/create'),
            'edit'   => Pages\EditWblePlacement::route('/{record}/edit'),
            'view'   => Pages\ViewWblePlacement::route('/{record}'),
        ];
    }
}
