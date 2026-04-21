<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Crp;
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

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Users';
    protected static string|\UnitEnum|null $navigationGroup = 'Super Admin';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('User Details')
                ->columns(2)
                ->schema([
                    Select::make('crp_id')
                        ->label('CRP')
                        ->options(fn () => Crp::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(fn ($get) => ! $get('is_super_admin')),

                    Select::make('role')
                        ->options([
                            'admin'            => 'CRP Admin',
                            'senior_counselor' => 'Senior Counselor',
                            'counselor'        => 'Counselor',
                            'readonly'         => 'Read Only',
                        ])
                        ->default('counselor')
                        ->required(),

                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

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

                    Toggle::make('is_super_admin')
                        ->label('Super Admin')
                        ->default(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('crp.name')
                    ->label('CRP')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable(),

                TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin'            => 'warning',
                        'senior_counselor' => 'success',
                        'counselor'        => 'info',
                        'readonly'         => 'gray',
                        default            => 'gray',
                    }),

                IconColumn::make('mfa_enabled')
                    ->label('MFA')
                    ->boolean(),

                IconColumn::make('is_super_admin')
                    ->label('Super Admin')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('crp')
                    ->relationship('crp', 'name'),

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

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('is_super_admin', false);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
