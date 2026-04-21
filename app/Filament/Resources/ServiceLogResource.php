<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\EnforcesRoles;
use App\Filament\Resources\ServiceLogResource\Pages;
use App\Models\Authorization;
use App\Models\Client;
use App\Models\ServiceLog;
use App\Services\CurriculumBlockingService;
use App\Services\DeadlineAlertService;
use App\Services\RulesEngineService;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ServiceLogResource extends Resource
{
    use EnforcesRoles;

    protected static ?string $model = ServiceLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Service Logs';
    protected static string|\UnitEnum|null $navigationGroup = 'Case Management';
    protected static ?int $navigationSort = 3;

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
                        ->required()
                        ->reactive(),

                    Select::make('authorization_id')
                        ->label('Authorization')
                        ->options(fn ($get) =>
                            Authorization::where('crp_id', auth()->user()->crp_id)
                                ->when($get('client_id'), fn ($q, $clientId) =>
                                    $q->where('client_id', $clientId)
                                )
                                ->where('status', 'active')
                                ->get()
                                ->mapWithKeys(fn (Authorization $a) => [
                                    $a->id => $a->authorization_number . ' (' . $a->service_code . ')',
                                ])
                        )
                        ->searchable()
                        ->required(),
                ]),

            Section::make('Service Details')
                ->columns(2)
                ->schema([
                    Select::make('form_type')
                        ->label('Form Type')
                        ->options([
                            '963X' => '963X — Pre-ETS Service Log',
                            '964X' => '964X — WBLE Service Log',
                            '122X' => '122X — Counseling & Guidance',
                        ])
                        ->required(),

                    Select::make('service_code')
                        ->label('Service Code')
                        ->options([
                            '963X' => '963X',
                            '964X' => '964X',
                            '122X' => '122X',
                        ])
                        ->required(),

                    DatePicker::make('service_date')
                        ->label('Service Date')
                        ->required(),

                    Select::make('report_type')
                        ->label('Report Type')
                        ->options([
                            'initial'    => 'Initial',
                            'midpoint'   => 'Midpoint',
                            'conclusion' => 'Conclusion',
                        ]),
                ]),

            Section::make('Time & Units')
                ->columns(3)
                ->schema([
                    TimePicker::make('start_time')
                        ->label('Start Time')
                        ->required(),

                    TimePicker::make('end_time')
                        ->label('End Time')
                        ->required()
                        ->after('start_time'),

                    TextInput::make('units')
                        ->label('Units')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                ]),

            Section::make('Curriculum')
                ->columns(1)
                ->schema([
                    Select::make('curriculum_id')
                        ->label('Curriculum Used')
                        ->options(fn () =>
                            app(CurriculumBlockingService::class)
                                ->getValidCurricula(auth()->user()->crp_id)
                                ->pluck('title', 'id')
                        )
                        ->searchable(),
                ]),

            Section::make('Notes')
                ->schema([
                    Textarea::make('notes')
                        ->label('Service Notes')
                        ->helperText('Minimum 50 characters required for submission.')
                        ->rows(5)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('client.last_name')
                    ->label('Client')
                    ->getStateUsing(fn (ServiceLog $record): string =>
                        $record->client->last_name . ', ' . $record->client->first_name
                    )
                    ->searchable(query: fn ($query, string $search) =>
                        $query->whereHas('client', fn ($q) =>
                            $q->whereRaw("LOWER(last_name) LIKE ?", ["%{$search}%"])
                              ->orWhereRaw("LOWER(first_name) LIKE ?", ["%{$search}%"])
                        )
                    )
                    ->sortable(query: fn ($query, string $direction) =>
                        $query->join('clients', 'service_logs.client_id', '=', 'clients.id')
                              ->orderBy('clients.last_name', $direction)
                    ),

                TextColumn::make('form_type')
                    ->label('Form')
                    ->badge()
                    ->color('info'),

                TextColumn::make('service_code')
                    ->label('Code')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('units')
                    ->label('Units')
                    ->sortable(),

                TextColumn::make('report_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'draft'     => 'gray',
                        'ready'     => 'info',
                        'submitted' => 'warning',
                        'approved'  => 'success',
                        'rejected'  => 'danger',
                        default     => 'gray',
                    }),

                TextColumn::make('deadline_status')
                    ->label('Deadline')
                    ->getStateUsing(fn (ServiceLog $record): string => match (
                        app(DeadlineAlertService::class)->getStatus($record)
                    ) {
                        'overdue' => 'Overdue',
                        'warning' => app(DeadlineAlertService::class)->getDaysRemaining($record) . 'd left',
                        'on_track' => app(DeadlineAlertService::class)->getDaysRemaining($record) . 'd left',
                        default   => 'Submitted',
                    })
                    ->badge()
                    ->color(fn (ServiceLog $record): string => match (
                        app(DeadlineAlertService::class)->getStatus($record)
                    ) {
                        'overdue'  => 'danger',
                        'warning'  => 'warning',
                        'on_track' => 'success',
                        default    => 'gray',
                    }),

                TextColumn::make('flags')
                    ->label('Flags')
                    ->getStateUsing(function (ServiceLog $record): string {
                        $result = app(RulesEngineService::class)->evaluateLogs($record->crp_id);
                        $logResult = $result[$record->id] ?? null;

                        if (! $logResult || $logResult['status'] === RulesEngineService::STATUS_READY) {
                            return 'OK';
                        }

                        $flagLabels = array_map(fn ($f) => match ($f['flag']) {
                            'missing_authorization' => 'No Auth',
                            'missing_signature'     => 'No Signature',
                            'missing_payroll'       => 'No Payroll',
                            default                 => $f['flag'],
                        }, $logResult['flags']);

                        return implode(', ', $flagLabels);
                    })
                    ->badge()
                    ->color(fn (ServiceLog $record): string => match (true) {
                        $record->report_status === 'submitted',
                        $record->report_status === 'approved' => 'gray',
                        default => (function () use ($record) {
                            $result = app(RulesEngineService::class)->evaluateLogs($record->crp_id);
                            return ($result[$record->id]['status'] ?? 'READY') === 'BLOCKED'
                                ? 'danger'
                                : 'success';
                        })(),
                    }),

                TextColumn::make('locked_at')
                    ->label('Locked')
                    ->getStateUsing(fn (ServiceLog $record): string =>
                        $record->isLocked() ? '🔒' : '—'
                    ),

                TextColumn::make('user.name')
                    ->label('Counselor')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('report_status')
                    ->label('Status')
                    ->options([
                        'draft'     => 'Draft',
                        'ready'     => 'Ready',
                        'submitted' => 'Submitted',
                        'approved'  => 'Approved',
                        'rejected'  => 'Rejected',
                    ]),

                SelectFilter::make('form_type')
                    ->label('Form Type')
                    ->options([
                        '963X' => '963X',
                        '964X' => '964X',
                        '122X' => '122X',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->disabled(fn (ServiceLog $record): bool => $record->isLocked()),
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('service_date', 'desc');
    }

    public static function getRelationManagers(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListServiceLogs::route('/'),
            'create' => Pages\CreateServiceLog::route('/create'),
            'edit'   => Pages\EditServiceLog::route('/{record}/edit'),
            'view'   => Pages\ViewServiceLog::route('/{record}'),
        ];
    }
}
