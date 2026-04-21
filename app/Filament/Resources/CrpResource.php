<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrpResource\Pages;
use App\Models\Crp;
use Filament\Forms\Components\TextInput;
use Illuminate\Validation\Rule;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CrpResource extends Resource
{
    protected static ?string $model = Crp::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'CRPs';
    protected static ?string $modelLabel = 'CRP';
    protected static ?string $pluralModelLabel = 'CRPs';
    protected static string|\UnitEnum|null $navigationGroup = 'Super Admin';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Organization')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->rules(fn ($record) => [
                            Rule::unique('crps', 'name')->ignore($record?->id),
                        ])
                        ->validationMessages([
                            'unique' => 'A CRP with this name already exists.',
                        ]),

                    TextInput::make('vendor_id')
                        ->label('Vendor ID')
                        ->maxLength(50),

                    TextInput::make('email')
                        ->email()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(30),

                    TextInput::make('address')
                        ->columnSpanFull()
                        ->maxLength(255),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
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

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vendor_id')
                    ->label('Vendor ID')
                    ->fontFamily('mono')
                    ->toggleable(),

                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->sortable(),

                TextColumn::make('clients_count')
                    ->label('Clients')
                    ->counts('clients')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCrps::route('/'),
            'create' => Pages\CreateCrp::route('/create'),
            'edit'   => Pages\EditCrp::route('/{record}/edit'),
            'view'   => Pages\ViewCrp::route('/{record}'),
        ];
    }
}
