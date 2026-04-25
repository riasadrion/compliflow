<?php

namespace App\Services;

use Carbon\Carbon;

class FieldResolverService
{
    public function formatDate(?string $date): string
    {
        if (! $date) {
            return '';
        }

        return Carbon::parse($date)->format('m/d/Y');
    }

    public function formatDateRange(?string $start, ?string $end): string
    {
        if (! $start) {
            return '';
        }

        $s = Carbon::parse($start)->format('m/d/Y');

        if (! $end || $start === $end) {
            return $s;
        }

        return $s . ' – ' . Carbon::parse($end)->format('m/d/Y');
    }

    public function calculateAge(?string $dob): string
    {
        if (! $dob) {
            return '';
        }

        return (string) Carbon::parse($dob)->age;
    }

    public function getNYSFiscalYear(): string
    {
        $now = Carbon::now();

        // NYS fiscal year runs April 1 – March 31
        if ($now->month >= 4) {
            return $now->year . '-' . ($now->year + 1);
        }

        return ($now->year - 1) . '-' . $now->year;
    }

    public function formatPhone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10) {
            return '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6);
        }

        return $phone;
    }

    public function yesNo(mixed $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    public function skillEntry(?int $level, ?string $notes): string
    {
        $parts = [];

        if ($level !== null) {
            $parts[] = 'Level ' . $level;
        }

        if ($notes) {
            $parts[] = $notes;
        }

        return implode(' — ', $parts);
    }
}
