<?php

namespace App\Services;

use App\Models\ServiceLog;

/**
 * Validates a service log has all required fields before it can be marked Ready.
 *
 * VR-121X (963X): client, dob, service_date, service_code, counselor, auth number,
 *                 auth end date, notes (min 50 chars), curriculum_used
 * VR-122X (964X): all 121X fields + career_interest_area, assessment_tools, activities_completed
 * 122X:           client, dob, service_date, service_code, counselor, auth number, auth end date, notes
 */
class CompletenessValidatorService
{
    /**
     * Validate the log. Returns ['valid' => true] or ['valid' => false, 'errors' => [...]]
     */
    public function validate(ServiceLog $log): array
    {
        $errors = [];

        // Load relationships
        $log->loadMissing(['client', 'authorization', 'user', 'curriculum']);

        // ── Fields required for ALL form types ────────────────────────────

        if (! $log->client) {
            $errors[] = 'Client is required.';
        } else {
            if (! $log->client->first_name || ! $log->client->last_name) {
                $errors[] = 'Client name is incomplete.';
            }
            if (! $log->client->dob) {
                $errors[] = 'Client date of birth is missing.';
            }
        }

        if (! $log->service_date) {
            $errors[] = 'Service date is required.';
        }

        if (! $log->service_code) {
            $errors[] = 'Service code is required.';
        }

        if (! $log->user) {
            $errors[] = 'Counselor (assigned user) is required.';
        }

        if (! $log->authorization) {
            $errors[] = 'Authorization is required.';
        } else {
            if (! $log->authorization->authorization_number) {
                $errors[] = 'Authorization number is missing.';
            }
            if (! $log->authorization->end_date) {
                $errors[] = 'Authorization end date is missing.';
            }
        }

        if (! $log->notes || strlen($log->notes) < 50) {
            $errors[] = 'Service notes must be at least 50 characters.';
        }

        // ── 963X (VR-121X) specific ────────────────────────────────────────

        if (in_array($log->form_type, ['963X', '964X'])) {
            if (! $log->curriculum_id) {
                $errors[] = 'Curriculum used is required for ' . $log->form_type . ' forms.';
            }
        }

        // ── 964X (VR-122X) specific ────────────────────────────────────────

        if ($log->form_type === '964X') {
            $custom = $log->custom_fields ?? [];

            if (empty($custom['career_interest_area'])) {
                $errors[] = 'Career interest area is required for 964X forms.';
            }
            if (empty($custom['assessment_tools'])) {
                $errors[] = 'Assessment tools are required for 964X forms.';
            }
            if (empty($custom['activities_completed'])) {
                $errors[] = 'Activities completed is required for 964X forms.';
            }
        }

        return empty($errors)
            ? ['valid' => true, 'errors' => []]
            : ['valid' => false, 'errors' => $errors];
    }
}
