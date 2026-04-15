<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\EnforcesRoles;
use App\Filament\Resources\WbleEmployerResource\Pages;
use App\Models\WbleEmployer;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class WbleEmployerResource extends Resource
{
    use EnforcesRoles;

    protected static ?string $model = WbleEmployer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'WBLE Employers';
    protected static string|\UnitEnum|null $navigationGroup = 'WBLE';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ! $user->isSuperAdmin() && $user->crp_id !== null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Employer Information')
                ->columns(2)
                ->schema([
                    TextInput::make('employer_name')
                        ->label('Employer Name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('employer_address')
                        ->label('Address')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('ein')
                        ->label('EIN')
                        ->maxLength(20)
                        ->placeholder('XX-XXXXXXX'),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),

            Section::make('Contact')
                ->columns(2)
                ->schema([
                    TextInput::make('contact_name')
                        ->label('Contact Name')
                        ->maxLength(255),

                    TextInput::make('contact_phone')
                        ->label('Phone')
                        ->tel()
                        ->maxLength(30),

                    TextInput::make('contact_email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employer_name')
                    ->label('Employer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->toggleable(),

                TextColumn::make('contact_email')
                    ->label('Email')
                    ->toggleable(),

                TextColumn::make('ein')
                    ->label('EIN')
                    ->fontFamily('mono')
                    ->toggleable(),

                TextColumn::make('placements_count')
                    ->label('Placements')
                    ->counts('placements')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
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
            ->defaultSort('employer_name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWbleEmployers::route('/'),
            'create' => Pages\CreateWbleEmployer::route('/create'),
            'edit'   => Pages\EditWbleEmployer::route('/{record}/edit'),
            'view'   => Pages\ViewWbleEmployer::route('/{record}'),
        ];
    }
}
