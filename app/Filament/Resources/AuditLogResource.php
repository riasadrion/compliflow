<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\CrpAuditLog;
use App\Services\CryptographicAuditService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    protected static ?string $model = CrpAuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Audit Log';
    protected static string|\UnitEnum|null $navigationGroup = 'Compliance';
    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ! $user->isSuperAdmin() && $user->crp_id !== null;
    }

    // Read-only — no create/edit
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                CrpAuditLog::withoutCrpScope()
                    ->where('crp_id', auth()->user()->crp_id)
                    ->with('user')
            )
            ->defaultSort('sequence', 'desc')
            ->columns([
                TextColumn::make('sequence')
                    ->label('#')
                    ->sortable()
                    ->width('60px')
                    ->fontFamily('mono'),

                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('M j, Y H:i:s')
                    ->sortable()
                    ->timezone(config('app.timezone')),

                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'delete')  => 'danger',
                        str_contains($state, 'export')  => 'warning',
                        str_contains($state, 'login')   => 'info',
                        str_contains($state, 'mfa')     => 'info',
                        str_contains($state, 'create')  => 'success',
                        str_contains($state, 'update')  => 'primary',
                        $state === 'GENESIS'            => 'gray',
                        default                         => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->default('System')
                    ->searchable(),

                TextColumn::make('entity_type')
                    ->label('Entity')
                    ->formatStateUsing(fn (?string $state, CrpAuditLog $record): string =>
                        $state ? ucfirst($state) . ($record->entity_id ? " #{$record->entity_id}" : '') : '—'
                    ),

                TextColumn::make('classification')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'security'    => 'danger',
                        'compliance'  => 'warning',
                        'operational' => 'gray',
                        default       => 'gray',
                    }),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('current_hash')
                    ->label('Hash')
                    ->fontFamily('mono')
                    ->formatStateUsing(fn (?string $state): string =>
                        $state ? substr($state, 0, 12) . '…' : '—'
                    )
                    ->tooltip(fn (?string $state): string => $state ?? '')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('chain_valid')
                    ->label('Chain')
                    ->boolean()
                    ->getStateUsing(function (CrpAuditLog $record): bool {
                        if ($record->action === 'GENESIS') return true;
                        if (! $record->previous_hash || ! $record->current_hash) return false;

                        $payload = json_encode([
                            'crp_id'      => $record->crp_id,
                            'user_id'     => $record->user_id,
                            'action'      => $record->action,
                            'entity_type' => $record->entity_type,
                            'entity_id'   => $record->entity_id,
                            'sequence'    => $record->sequence,
                        ]);

                        return hash('sha256', $record->previous_hash . $payload) === $record->current_hash;
                    })
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                SelectFilter::make('classification')
                    ->options([
                        'security'    => 'Security',
                        'compliance'  => 'Compliance',
                        'operational' => 'Operational',
                    ]),

                SelectFilter::make('action')
                    ->label('Action')
                    ->options(
                        CrpAuditLog::withoutCrpScope()
                            ->where('crp_id', auth()->user()?->crp_id)
                            ->distinct()
                            ->pluck('action', 'action')
                            ->toArray()
                    ),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
