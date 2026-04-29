<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\EnforcesRoles;
use App\Filament\Resources\ServiceLogResource\Pages;
use App\Models\Authorization;
use App\Models\Client;
use App\Models\ServiceLog;
use App\Services\CurriculumBlockingService;
use App\Services\DeadlineAlertService;
use App\Services\CryptographicAuditService;
use App\Jobs\GenerateFormJob;
use App\Models\GeneratedForm;
use App\Services\PdfOverlayService;
use App\Services\S3SecureStorageService;
use App\Services\RulesEngineService;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
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
                            '127X' => '127X — Individual Pre-ETS',
                            '963X' => '963X — Pre-ETS Service Log',
                            '964X' => '964X — WBLE Service Log',
                            '122X' => '122X — Counseling & Guidance',
                        ])
                        ->required()
                        ->live(),

                    Select::make('service_code')
                        ->label('Service Code')
                        ->options([
                            '127X' => '127X',
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

            Section::make('Eligibility')
                ->columns(1)
                ->schema([
                    Select::make('custom_fields.eligibility_type')
                        ->label('Eligibility Status')
                        ->options([
                            'eligible'              => 'Eligible Student',
                            'potentially_eligible'  => 'Potentially Eligible Student',
                        ])
                        ->default('eligible')
                        ->required(),
                ]),

            // ── 127X-specific: Skills Assessment ──────────────────────────
            Section::make('127X — Skills Assessment')
                ->visible(fn ($get): bool => $get('form_type') === '127X')
                ->columnSpanFull()
                ->schema([
                    Fieldset::make('Skill Ratings')
                        ->schema(self::buildSkillFields())
                        ->columns(2),

                    Toggle::make('custom_fields.curriculum_approved')
                        ->label('Workplace Readiness curriculum approved by District Office?'),

                    Toggle::make('custom_fields.competency_demonstrated')
                        ->label('Customer demonstrated increased competency in rated areas?'),

                    Textarea::make('custom_fields.additional_comments')
                        ->label('Additional Comments / Recommendations')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            // ── 963X-specific: Pre-ETS Work Experience ────────────────────
            Section::make('963X — Pre-ETS Work Experience')
                ->visible(fn ($get): bool => $get('form_type') === '963X')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Textarea::make('custom_fields.employer_name_address')
                        ->label('Employer-Based Work Experience Business Name & Address')
                        ->rows(2)
                        ->columnSpanFull(),
                    DatePicker::make('custom_fields.work_start_date')
                        ->label('Work Experience Start Date'),
                    DatePicker::make('custom_fields.work_end_date')
                        ->label('Anticipated Completion Date'),
                    DatePicker::make('custom_fields.last_contact_date')
                        ->label('Last Date of Contact (if dropped out)'),
                    TextInput::make('custom_fields.work_schedule')
                        ->label('Work Experience Schedule')
                        ->columnSpanFull(),
                    TextInput::make('custom_fields.total_hours_to_date')
                        ->label('Total Hours Utilized to Date')
                        ->numeric(),
                ]),

            // ── 964X-specific: WBLE Activities & Supports ─────────────────
            Section::make('964X — WBLE Activities & Supports')
                ->visible(fn ($get): bool => $get('form_type') === '964X')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Textarea::make('custom_fields.employer_name_address')
                        ->label('Employer-Based Work Experience Business Name & Address')
                        ->rows(2)
                        ->columnSpanFull(),
                    DatePicker::make('custom_fields.work_start_date')
                        ->label('Work Experience Start Date'),
                    DatePicker::make('custom_fields.work_end_date')
                        ->label('Anticipated Completion Date'),
                    DatePicker::make('custom_fields.last_contact_date')
                        ->label('Last Date of Contact (if dropped out)'),
                    TextInput::make('custom_fields.work_schedule')
                        ->label('Work Experience Schedule'),
                    TextInput::make('custom_fields.total_hours_to_date')
                        ->label('Total Hours Utilized to Date')
                        ->numeric(),

                    Repeater::make('custom_fields.activities')
                        ->label('Activity / Skill Development (up to 8)')
                        ->simple(TextInput::make('value')->placeholder('Activity'))
                        ->maxItems(8)
                        ->columnSpanFull(),

                    Repeater::make('custom_fields.supports')
                        ->label('Support Provided / Outcome (up to 8)')
                        ->simple(TextInput::make('value')->placeholder('Support'))
                        ->maxItems(8)
                        ->columnSpanFull(),
                ]),

            // ── 122X-specific: Counseling & Guidance ──────────────────────
            Section::make('122X — Counseling & Guidance')
                ->visible(fn ($get): bool => $get('form_type') === '122X')
                ->columnSpanFull()
                ->schema([
                    Select::make('custom_fields.service_mode')
                        ->label('Service Mode')
                        ->options([
                            'individual' => 'Individual',
                            'group'      => 'Group',
                        ])
                        ->default('individual')
                        ->required(),

                    CheckboxList::make('custom_fields.services_delivered')
                        ->label('Services Delivered (check all that apply)')
                        ->options([
                            'vocational_interest'        => 'Vocational Interest Inventory Results',
                            'labor_market'               => 'Labor Market',
                            'in_demand_industries'       => 'In-demand Industries and Occupations',
                            'career_pathways'            => 'Identification of Career Pathways',
                            'career_awareness'           => 'Career Awareness and Skill Development',
                            'career_speakers'            => 'Career Speakers',
                            'career_student_org'         => 'Career Student Organization',
                            'one_stop_orientation'       => 'One Stop / DOL Orientation',
                            'workforce_skills'           => 'Workforce Skills for Specific Jobs',
                            'non_traditional_employment' => 'Non-traditional Employment Options',
                        ])
                        ->columns(2)
                        ->columnSpanFull(),

                    Textarea::make('custom_fields.narrative')
                        ->label('Narrative — Student\'s Experience')
                        ->rows(5)
                        ->columnSpanFull(),
                ]),

            Section::make('Notes')
                ->columnSpanFull()
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

                TextColumn::make('pdf_status')
                    ->label('PDF')
                    ->badge()
                    ->getStateUsing(function (ServiceLog $record): string {
                        $form = GeneratedForm::where('service_log_id', $record->id)
                            ->where('form_type', strtoupper($record->form_type))
                            ->first();
                        return $form?->status ?? 'not generated';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed'  => 'success',
                        'processing' => 'warning',
                        'failed'     => 'danger',
                        default      => 'gray',
                    }),

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
                        '127X' => '127X',
                        '963X' => '963X',
                        '964X' => '964X',
                        '122X' => '122X',
                    ]),
            ])
            ->actions([
                Action::make('generate_pdf')
                    ->label('Generate PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->visible(function (ServiceLog $record): bool {
                        $form = GeneratedForm::where('service_log_id', $record->id)
                            ->where('form_type', strtoupper($record->form_type))
                            ->first();
                        return ! $form && in_array($record->report_status, ['draft', 'ready']);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Generate PDF')
                    ->modalDescription('Generation runs in the background. The PDF status will update when complete.')
                    ->modalSubmitActionLabel('Generate')
                    ->action(function (ServiceLog $record) {
                        GenerateFormJob::dispatch($record->id, $record->form_type, auth()->id());

                        Notification::make()
                            ->title('PDF generation queued')
                            ->body('The PDF will appear once generation completes.')
                            ->success()
                            ->send();
                    }),

                Action::make('retry_pdf')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(function (ServiceLog $record): bool {
                        return GeneratedForm::where('service_log_id', $record->id)
                            ->where('form_type', strtoupper($record->form_type))
                            ->where('status', 'failed')
                            ->exists();
                    })
                    ->requiresConfirmation()
                    ->action(function (ServiceLog $record) {
                        GenerateFormJob::dispatch($record->id, $record->form_type, auth()->id());

                        Notification::make()
                            ->title('Retry queued')
                            ->success()
                            ->send();
                    }),

                Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(function (ServiceLog $record): bool {
                        return GeneratedForm::where('service_log_id', $record->id)
                            ->where('form_type', strtoupper($record->form_type))
                            ->where('status', 'completed')
                            ->exists();
                    })
                    ->action(function (ServiceLog $record) {
                        $existing = GeneratedForm::where('service_log_id', $record->id)
                            ->where('form_type', strtoupper($record->form_type))
                            ->where('status', 'completed')
                            ->first();

                        if (! $existing) {
                            Notification::make()->title('PDF not ready yet')->danger()->send();
                            return;
                        }

                        app(CryptographicAuditService::class)->log(
                            $record->crp_id,
                            auth()->id(),
                            'phi_export',
                            ServiceLog::class,
                            $record->id,
                            [
                                'form_type'      => $record->form_type,
                                'pdf_hash'       => $existing->pdf_hash,
                                'classification' => 'phi',
                            ],
                        );

                        $url = app(S3SecureStorageService::class)->presignedUrl($existing);
                        return redirect()->away($url);
                    }),

                EditAction::make()
                    ->disabled(fn (ServiceLog $record): bool => $record->isLocked()),
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('service_date', 'desc')
            ->poll('5s');
    }

    public static function getRelationManagers(): array
    {
        return [];
    }

    /**
     * Build the 14 skill fields for 127X — each gets a 1-4 level + free-text observations.
     */
    protected static function buildSkillFields(): array
    {
        $skills = [
            'financial_literacy'  => 'Financial Literacy',
            'independent_travel'  => 'Independent Travel',
            'personal_appearance' => 'Personal Appearance',
            'time_management'     => 'Time Management',
            'communication'       => 'Communication',
            'social_interaction'  => 'Social Interaction',
            'attention_focus'     => 'Attention & Focus',
            'problem_solving'     => 'Problem Solving',
            'teamwork'            => 'Teamwork',
            'job_seeking_skills'  => 'Job Seeking Skills',
            'interview_skills'    => 'Interview Skills',
            'computer_literacy'   => 'Computer Literacy',
            'task_completion'     => 'Task Completion',
            'other'               => 'Other',
        ];

        $fields = [];
        foreach ($skills as $key => $label) {
            $fields[] = Select::make("custom_fields.skills.{$key}.level")
                ->label($label . ' — Progress Level')
                ->options([
                    1 => '1 — Does not yet meet standard',
                    2 => '2 — Meets acceptable standard',
                    3 => '3 — Approaching excellence',
                    4 => '4 — Standard of excellence',
                ])
                ->placeholder('Not rated');

            $fields[] = Textarea::make("custom_fields.skills.{$key}.notes")
                ->label($label . ' — Observations')
                ->rows(2);
        }

        return $fields;
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
