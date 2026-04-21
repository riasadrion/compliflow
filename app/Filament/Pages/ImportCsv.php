<?php

namespace App\Filament\Pages;

use App\Services\CsvImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class ImportCsv extends Page
{

    protected string $view = 'filament.pages.import-csv';

    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'CSV Import';
    protected static ?string $title = 'CSV Import';
    protected static string|\UnitEnum|null $navigationGroup = 'Tools';
    protected static ?int    $navigationSort  = 1;

    public ?array $data = [];

    public ?array $result = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ! $user->isSuperAdmin() && in_array($user->role, ['admin', 'senior_counselor', 'counselor']);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                FileUpload::make('csv_file')
                    ->label('CSV File')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->maxSize(5120) // 5MB
                    ->required()
                    ->helperText('Upload a CSV with columns: client_id, first_name, last_name, dob, auth_number, auth_start, auth_end, service_code, service_date, start_time, end_time, hours, form_type'),
            ])
            ->statePath('data');
    }

    public function import(): void
    {
        $this->form->validate();

        $path = $this->data['csv_file'];

        // FileUpload stores the path — get the temp file
        $file = new \Illuminate\Http\UploadedFile(
            storage_path('app/public/' . $path),
            basename($path),
            null,
            null,
            true
        );

        try {
            $result = app(CsvImportService::class)->import($file);
            $this->result = $result;

            Notification::make()
                ->title("Import complete: {$result['imported']} imported, {$result['skipped']} skipped")
                ->success()
                ->send();

            $this->form->fill();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Import failed: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
