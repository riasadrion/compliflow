<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\EnforcesRoles;
use App\Filament\Resources\WblePayrollResource\Pages;
use App\Models\Client;
use App\Models\WbleEmployer;
use App\Models\WblePlacement;
use App\Models\WblePayrollRecord;
use App\Services\ReimbursementDeadlineService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

class WblePayrollResource extends Resource
{
    use EnforcesRoles;

    protected static ?string $model = WblePayrollRecord::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Payroll Records';
    protected static string|\UnitEnum|null $navigationGroup = 'WBLE';
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ! $user->isSuperAdmin() && $user->crp_id !== null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Client & Placement')
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

                    Select::make('wble_employer_id')
                        ->label('Employer')
                        ->options(fn () =>
                            WbleEmployer::where('crp_id', auth()->user()->crp_id)
                                ->where('is_active', true)
                                ->pluck('employer_name', 'id')
                        )
                        ->searchable()
                        ->required(),

                    Select::make('wble_placement_id')
                        ->label('Placement')
                        ->options(fn ($get) =>
                            WblePlacement::where('crp_id', auth()->user()->crp_id)
                                ->when($get('client_id'), fn ($q, $id) => $q->where('client_id', $id))
                                ->get()
                                ->mapWithKeys(fn (WblePlacement $p) => [
                                    $p->id => $p->job_title . ' (' . $p->planned_start_date->format('M j, Y') . ')',
                                ])
                        )
                        ->searchable()
                        ->required(),
                ]),

            Section::make('Pay Period')
                ->columns(2)
                ->schema([
                    DatePicker::make('pay_period_start')
                        ->label('Period Start')
                        ->required(),

                    DatePicker::make('pay_period_end')
                        ->label('Period End')
                        ->required()
                        ->after('pay_period_start'),

                    DatePicker::make('pay_date')
                        ->label('Pay Date')
                        ->required(),

                    DateTimePicker::make('employer_signature_date')
                        ->label('Employer Signature Date'),
                ]),

            Section::make('Wages & Reimbursement')
                ->columns(2)
                ->schema([
                    TextInput::make('hours_worked')
                        ->label('Hours Worked')
                        ->numeric()
                        ->required()
                        ->minValue(0.01),

                    TextInput::make('wage_rate')
                        ->label('Wage Rate ($/hr)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->prefix('$'),

                    TextInput::make('gross_wages')
                        ->label('Gross Wages')
                        ->numeric()
                        ->required()
                        ->prefix('$'),

                    TextInput::make('reimbursement_amount')
                        ->label('Reimbursement Amount')
                        ->numeric()
                        ->prefix('$'),

                    Select::make('reimbursement_status')
                        ->label('Reimbursement Status')
                        ->options([
                            'pending'   => 'Pending',
                            'submitted' => 'Submitted',
                            'paid'      => 'Paid',
                        ])
                        ->default('pending')
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.last_name')
                    ->label('Client')
                    ->getStateUsing(fn (WblePayrollRecord $record): string =>
                        $record->client->last_name . ', ' . $record->client->first_name
                    )
                    ->searchable(query: fn ($query, string $search) =>
                        $query->whereHas('client', fn ($q) =>
                            $q->whereRaw("LOWER(last_name) LIKE ?", ["%{$search}%"])
                              ->orWhereRaw("LOWER(first_name) LIKE ?", ["%{$search}%"])
                        )
                    ),

                TextColumn::make('pay_period_start')
                    ->label('Pay Period')
                    ->getStateUsing(fn (WblePayrollRecord $record): string =>
                        $record->pay_period_start->format('M j') . ' – ' . $record->pay_period_end->format('M j, Y')
                    )
                    ->sortable(query: fn ($query, string $direction) =>
                        $query->orderBy('pay_period_start', $direction)
                    ),

                TextColumn::make('gross_wages')
                    ->label('Gross')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('reimbursement_deadline')
                    ->label('Deadline')
                    ->date('M j, Y')
                    ->sortable()
                    ->color(fn (WblePayrollRecord $record): string => match ($record->deadline_status) {
                        'overdue'  => 'danger',
                        'critical' => 'danger',
                        'warning'  => 'warning',
                        default    => 'gray',
                    }),

                TextColumn::make('deadline_status')
                    ->label('Deadline Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'on_track' => 'On Track',
                        'warning'  => 'Warning',
                        'critical' => 'Critical',
                        'overdue'  => 'Overdue',
                        default    => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'on_track' => 'success',
                        'warning'  => 'warning',
                        'critical' => 'danger',
                        'overdue'  => 'danger',
                        default    => 'gray',
                    }),

                TextColumn::make('reimbursement_status')
                    ->label('Reimbursement')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'paid'      => 'success',
                        'submitted' => 'info',
                        'pending'   => 'gray',
                        default     => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('deadline_status')
                    ->label('Deadline Status')
                    ->options([
                        'on_track' => 'On Track',
                        'warning'  => 'Warning',
                        'critical' => 'Critical',
                        'overdue'  => 'Overdue',
                    ]),

                SelectFilter::make('reimbursement_status')
                    ->label('Reimbursement')
                    ->options([
                        'pending'   => 'Pending',
                        'submitted' => 'Submitted',
                        'paid'      => 'Paid',
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
            ->defaultSort('reimbursement_deadline', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWblePayrolls::route('/'),
            'create' => Pages\CreateWblePayroll::route('/create'),
            'edit'   => Pages\EditWblePayroll::route('/{record}/edit'),
            'view'   => Pages\ViewWblePayroll::route('/{record}'),
        ];
    }
}
