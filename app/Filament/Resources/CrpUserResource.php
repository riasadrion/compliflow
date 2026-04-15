<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrpUserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * CRP-level user management — visible only to CRP admins.
 * Scoped to the authenticated user's CRP.
 */
class CrpUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Users';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ! $user->isSuperAdmin() && in_array($user->role, ['admin']);
    }

    public static function canCreate(): bool { return static::canAccess(); }
    public static function canEdit($record): bool { return static::canAccess(); }
    public static function canDelete($record): bool { return static::canAccess(); }
    public static function canDeleteAny(): bool { return static::canAccess(); }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Only show users belonging to the same CRP
        return parent::getEloquentQuery()
            ->where('crp_id', auth()->user()->crp_id)
            ->where('is_super_admin', false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('User Details')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Select::make('role')
                        ->options([
                            'admin'            => 'CRP Admin',
                            'senior_counselor' => 'Senior Counselor',
                            'counselor'        => 'Counselor',
                            'readonly'         => 'Read Only',
                        ])
                        ->default('counselor')
                        ->required(),

                    TextInput::make('password')
                        ->password()
                        ->dehydrateStateUsing(fn ($state) => bcrypt($state))
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Toggle::make('mfa_enabled')
                        ->label('MFA Enabled')
                        ->default(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable(),

                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin'            => 'CRP Admin',
                        'senior_counselor' => 'Senior Counselor',
                        'counselor'        => 'Counselor',
                        'readonly'         => 'Read Only',
                        default            => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'admin'            => 'warning',
                        'senior_counselor' => 'primary',
                        'counselor'        => 'info',
                        'readonly'         => 'gray',
                        default            => 'gray',
                    }),

                IconColumn::make('mfa_enabled')
                    ->label('MFA')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin'            => 'CRP Admin',
                        'senior_counselor' => 'Senior Counselor',
                        'counselor'        => 'Counselor',
                        'readonly'         => 'Read Only',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCrpUsers::route('/'),
            'create' => Pages\CreateCrpUser::route('/create'),
            'edit'   => Pages\EditCrpUser::route('/{record}/edit'),
        ];
    }
}
