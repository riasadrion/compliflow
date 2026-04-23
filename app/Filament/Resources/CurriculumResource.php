<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\EnforcesRoles;
use App\Filament\Resources\CurriculumResource\Pages;
use App\Models\Curriculum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CurriculumResource extends Resource
{
    use EnforcesRoles;

    protected static ?string $model = Curriculum::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Curricula';
    protected static string|\UnitEnum|null $navigationGroup = 'Case Management';
    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ! $user->isSuperAdmin() && $user->crp_id !== null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Curriculum Details')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('service_code')
                        ->label('Service Code')
                        ->options([
                            '121X' => '121X — Individual Pre-ETS',
                            '963X' => '963X — Pre-ETS',
                            '964X' => '964X — WBLE',
                            '122X' => '122X — Counseling & Guidance',
                        ])
                        ->required(),

                    Select::make('status')
                        ->options([
                            'draft'            => 'Draft',
                            'pending_approval' => 'Pending Approval',
                            'approved'         => 'Approved',
                            'expired'          => 'Expired',
                            'revoked'          => 'Revoked',
                        ])
                        ->default('draft')
                        ->required(),

                    Textarea::make('description')
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make('Approval')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('approved_at')
                        ->label('Approved At'),

                    DateTimePicker::make('expires_at')
                        ->label('Expires At'),

                    TextInput::make('approved_by')
                        ->label('Approved By')
                        ->maxLength(255),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('service_code')
                    ->label('Code')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending_approval' => 'Pending Approval',
                        default            => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'approved'         => 'success',
                        'pending_approval' => 'warning',
                        'draft'            => 'gray',
                        'expired'          => 'danger',
                        'revoked'          => 'danger',
                        default            => 'gray',
                    }),

                TextColumn::make('approved_by')
                    ->label('Approved By')
                    ->toggleable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date('M j, Y')
                    ->sortable()
                    ->color(fn (Curriculum $record): string => match (true) {
                        $record->expires_at === null        => 'gray',
                        $record->expires_at->isPast()      => 'danger',
                        $record->expires_at->diffInDays(now()) <= 30 => 'warning',
                        default                            => 'gray',
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft'            => 'Draft',
                        'pending_approval' => 'Pending Approval',
                        'approved'         => 'Approved',
                        'expired'          => 'Expired',
                        'revoked'          => 'Revoked',
                    ]),

                SelectFilter::make('service_code')
                    ->label('Service Code')
                    ->options([
                        '121X' => '121X',
                        '963X' => '963X',
                        '964X' => '964X',
                        '122X' => '122X',
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
            ->defaultSort('title', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCurricula::route('/'),
            'create' => Pages\CreateCurriculum::route('/create'),
            'edit'   => Pages\EditCurriculum::route('/{record}/edit'),
            'view'   => Pages\ViewCurriculum::route('/{record}'),
        ];
    }
}
