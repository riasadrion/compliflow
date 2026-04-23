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
                    ->disk('public')
                    ->directory('csv-imports')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->maxSize(5120)
                    ->required()
                    ->validationAttribute('CSV file')
                    ->helperText('Upload a CSV with columns: client_id, first_name, last_name, dob, auth_number, auth_start, auth_end, service_code, service_date, start_time, end_time, hours, form_type'),
            ])
            ->statePath('data');
    }

    public function import(): void
    {
        $raw = $this->data['csv_file'] ?? null;

        if (is_array($raw)) {
            $values = array_values($raw);
            $value = $values[0] ?? null;
        } else {
            $value = $raw;
        }

        if (empty($value)) {
            Notification::make()
                ->title('Please upload a CSV file before importing.')
                ->danger()
                ->send();
            return;
        }

        // Resolve the absolute path — handle TemporaryUploadedFile, stored path, or raw path
        $absolutePath = null;

        if ($value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $absolutePath = $value->getRealPath();
        } elseif (is_string($value)) {
            // Try public disk first (if Filament stored it there)
            $publicPath = \Illuminate\Support\Facades\Storage::disk('public')->path($value);
            if (file_exists($publicPath)) {
                $absolutePath = $publicPath;
            } else {
                // Fall back to Livewire temp storage
                $tmpPath = storage_path('app/private/livewire-tmp/' . basename($value));
                if (file_exists($tmpPath)) {
                    $absolutePath = $tmpPath;
                } elseif (file_exists($value)) {
                    $absolutePath = $value;
                }
            }
        }

        if (! $absolutePath || ! file_exists($absolutePath)) {
            Notification::make()
                ->title('Uploaded file not found. Please re-upload and try again.')
                ->danger()
                ->send();
            return;
        }

        try {
            $result = app(CsvImportService::class)->importFromPath($absolutePath);
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
