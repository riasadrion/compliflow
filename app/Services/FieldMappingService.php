<?php

namespace App\Services;

use App\Models\ServiceLog;

class FieldMappingService
{
    public function __construct(
        private readonly FieldResolverService $resolver,
    ) {}

    public function map(ServiceLog $log, string $type): array
    {
        $log->loadMissing(['client', 'authorization', 'user', 'curriculum']);

        return match (strtoupper($type)) {
            '127X' => $this->map127x($log),
            '963X' => $this->map963x($log),
            '964X' => $this->map964x($log),
            '122X' => $this->map122x($log),
            default => [],
        };
    }

    private function map127x(ServiceLog $log): array
    {
        $client  = $log->client;
        $auth    = $log->authorization;
        $user    = $log->user;
        $crp     = $user?->crp ?? null;
        $custom  = $log->custom_fields ?? [];
        $skills  = $custom['skills'] ?? [];
        $isPotentiallyEligible = ($custom['eligibility_type'] ?? '127X') === '1007X';

        return [
            // Eligibility checkboxes (radio-style)
            '127X Eligible Student'                 => $isPotentiallyEligible ? 'Off' : 'On',
            '1007X Potentially Eligible Student'    => $isPotentiallyEligible ? 'On' : 'Off',

            // Header IDs
            'Authorization'             => $auth?->authorization_number ?? '',
            'Aware Participant ID'      => $client?->external_id ?? '',

            // Office / Provider
            'VR District Office'        => $auth?->district_office ?? '',
            'Vendor'                    => $crp?->name ?? '',
            'SFS Vendor ID'             => $crp?->vendor_id ?? '',
            'VRC NameRow1'              => $auth?->vrc_name ?? '',
            'Report Date'               => $this->resolver->formatDate(now()->toDateString()),

            // Student
            'Student First Name'        => $client?->first_name ?? '',
            'Student Last Name'         => $client?->last_name ?? '',
            'Student Phone Number'      => $this->resolver->formatPhone($client?->phone),
            'Student Age'               => $this->resolver->calculateAge($client?->dob),
            'Student Email Address'     => $client?->email ?? '',

            // Service
            'Units of Service'          => (string) ($log->units ?? ''),
            'Dates of Service'          => $this->resolver->formatDate($log->service_date?->toDateString()),

            // Curriculum approval (Yes/No/Off button)
            'District Office Note Please maintain ACCESVR curriculum approval in the case record'
                => ! empty($custom['curriculum_approved']) ? 'Yes' : 'No',

            // Skills observations (free-text per skill)
            'Financial Literacy'        => $skills['financial_literacy']['notes']  ?? '',
            'Independent Travel'        => $skills['independent_travel']['notes']  ?? '',
            'Personal Appearance'       => $skills['personal_appearance']['notes'] ?? '',
            'Time Management'           => $skills['time_management']['notes']     ?? '',
            'Communication'             => $skills['communication']['notes']       ?? '',
            'Social Interaction'        => $skills['social_interaction']['notes']  ?? '',
            'Attention  Focus'          => $skills['attention_focus']['notes']     ?? '',
            'ProblemSolving'            => $skills['problem_solving']['notes']     ?? '',
            'Teamwork'                  => $skills['teamwork']['notes']            ?? '',
            'Job Seeking Skills'        => $skills['job_seeking_skills']['notes']  ?? '',
            'Interview Skills'          => $skills['interview_skills']['notes']    ?? '',
            'Computer Literacy'         => $skills['computer_literacy']['notes']   ?? '',
            'Task Completion'           => $skills['task_completion']['notes']     ?? '',
            'Other'                     => $skills['other']['notes']               ?? '',

            // Skills progress level (1-4)
            'Financial Literacy Progress Level'   => (string) ($skills['financial_literacy']['level']  ?? ''),
            'Independent Travel Progress Level'   => (string) ($skills['independent_travel']['level']  ?? ''),
            'Personal Appearance Progress Level'  => (string) ($skills['personal_appearance']['level'] ?? ''),
            'Time Management Progress Level'      => (string) ($skills['time_management']['level']     ?? ''),
            'Communication Progress Level'        => (string) ($skills['communication']['level']       ?? ''),
            'Social Interaction Progress Level'   => (string) ($skills['social_interaction']['level']  ?? ''),
            'Attention  Focus Progress Level'     => (string) ($skills['attention_focus']['level']     ?? ''),
            'Problem Solving Progress Level'      => (string) ($skills['problem_solving']['level']     ?? ''),
            'Teamwork Progress Level'             => (string) ($skills['teamwork']['level']            ?? ''),
            'Job Seeking Skills Progress Level'   => (string) ($skills['job_seeking_skills']['level']  ?? ''),
            'Interview Skills Progress Level'     => (string) ($skills['interview_skills']['level']    ?? ''),
            'Computer Literacy Progress Level'    => (string) ($skills['computer_literacy']['level']   ?? ''),
            'Task Completion Progress Level'      => (string) ($skills['task_completion']['level']     ?? ''),
            'Other Progress Level'                => (string) ($skills['other']['level']               ?? ''),

            // Summary
            'Has customer actively demonstrated increased competency in the rated areas'
                => ($custom['competency_demonstrated'] ?? false) ? 'Yes_2' : 'No_2',
            'Please include any additional comments or recommendations'
                => $custom['additional_comments'] ?? $log->notes ?? '',

            // Completed by
            'Printed Name'              => $user?->name ?? '',
            'Title'                     => $user?->title ?? '',
            'Phone'                     => $this->resolver->formatPhone($user?->phone),
            'Email'                     => $user?->email ?? '',
        ];
    }

    /**
     * Common header fields shared across 963X / 964X / 122X.
     */
    private function commonHeader(ServiceLog $log, string $eligibilityKey, string $potentialKey, bool $useVrcRow = false): array
    {
        $client = $log->client;
        $auth   = $log->authorization;
        $user   = $log->user;
        $crp    = $user?->crp ?? null;
        $custom = $log->custom_fields ?? [];
        $isPotentiallyEligible = ($custom['eligibility_type'] ?? 'eligible') === 'potentially_eligible';

        $header = [
            $eligibilityKey         => $isPotentiallyEligible ? 'Off' : 'On',
            $potentialKey           => $isPotentiallyEligible ? 'On'  : 'Off',
            'Authorization'         => $auth?->authorization_number ?? '',
            'Aware Participant ID'  => $client?->external_id ?? '',
            'VR District Office'    => $auth?->district_office ?? '',
            'Vendor'                => $crp?->name ?? '',
            'SFS Vendor ID'         => $crp?->vendor_id ?? '',
            'Report Date'           => $this->resolver->formatDate(now()->toDateString()),
            'Student First Name'    => $client?->first_name ?? '',
            'Student Last Name'     => $client?->last_name ?? '',
            'Student Phone Number'  => $this->resolver->formatPhone($client?->phone),
            'Student Email Address' => $client?->email ?? '',
            'Student Age'           => $this->resolver->calculateAge($client?->dob),
            'Printed Name'          => $user?->name ?? '',
            'Title'                 => $user?->title ?? '',
            'Phone'                 => $this->resolver->formatPhone($user?->phone),
            'Email'                 => $user?->email ?? '',
        ];

        $header[$useVrcRow ? 'VRC NameRow1' : 'VRC Name'] = $auth?->vrc_name ?? '';

        return $header;
    }

    private function map963x(ServiceLog $log): array
    {
        $custom = $log->custom_fields ?? [];

        return array_merge(
            $this->commonHeader($log, '963X Eligible Student', '1001X Potentially Eligible Student', false),
            [
                'EmployerBased Work Experience Business Name  Address' => $custom['employer_name_address'] ?? '',
                'Work Experience Start Date'                            => $this->resolver->formatDate($custom['work_start_date'] ?? null),
                'Anticipated Completion Date of Work Experience'        => $this->resolver->formatDate($custom['work_end_date'] ?? null),
                'Last Date of Contact if Customer Dropped Out of Service' => $this->resolver->formatDate($custom['last_contact_date'] ?? null),
                'Work Experience Schedule'                              => $custom['work_schedule'] ?? '',
                'Month of Service'                                      => $log->service_date?->format('F Y') ?? '',
                'Number of hours utilized for this report'              => (string) ($log->units ?? ''),
                'Total hours utilized to date'                          => (string) ($custom['total_hours_to_date'] ?? ''),
            ],
        );
    }

    private function map964x(ServiceLog $log): array
    {
        $custom = $log->custom_fields ?? [];
        $activities = $custom['activities'] ?? [];
        $supports   = $custom['supports'] ?? [];

        $rows = [];
        for ($i = 1; $i <= 8; $i++) {
            $rows["Activity  Skill DevelopmentRow{$i}"] = $activities[$i - 1] ?? '';
            $rows["Support Provided  OutcomeRow{$i}"]   = $supports[$i - 1] ?? '';
        }

        return array_merge(
            $this->commonHeader($log, '964X Eligible Student', '1002X Potentially Eligible Student', false),
            [
                'EmployerBased Work Experience Business Name  Address'    => $custom['employer_name_address'] ?? '',
                'Work Experience Start Date'                              => $this->resolver->formatDate($custom['work_start_date'] ?? null),
                'Anticipated Completion Date of Work Experience'          => $this->resolver->formatDate($custom['work_end_date'] ?? null),
                'Last Date of Contact if Customer Dropped Out of Service' => $this->resolver->formatDate($custom['last_contact_date'] ?? null),
                'Work Experience Schedule'                                => $custom['work_schedule'] ?? '',
                'Service Dates'                                           => $this->resolver->formatDate($log->service_date?->toDateString()),
                'Number of Trainer hours utilized for this report'        => (string) ($log->units ?? ''),
                'Total hours utilized to date'                            => (string) ($custom['total_hours_to_date'] ?? ''),
            ],
            $rows,
        );
    }

    private function map122x(ServiceLog $log): array
    {
        $custom = $log->custom_fields ?? [];
        $services = $custom['services_delivered'] ?? [];
        $isGroup  = ($custom['service_mode'] ?? 'individual') === 'group';

        $checkboxes = [
            'Vocational Interest Inventory Results'                   => in_array('vocational_interest', $services) ? 'Yes' : 'Off',
            'Labor Market'                                            => in_array('labor_market', $services) ? 'Yes' : 'Off',
            'In-demand Industries and Occupations'                    => in_array('in_demand_industries', $services) ? 'Yes' : 'Off',
            'Identification of Career Pathways of Interest to the Students' => in_array('career_pathways', $services) ? 'Yes' : 'Off',
            'Career Awareness and Skill Development'                  => in_array('career_awareness', $services) ? 'Yes' : 'Off',
            'Career Speakers'                                         => in_array('career_speakers', $services) ? 'Yes' : 'Off',
            'Career Student Organization'                             => in_array('career_student_org', $services) ? 'Yes' : 'Off',
            'Orientation and Registration with One Stop Career Center and Department of Labor services online' => in_array('one_stop_orientation', $services) ? 'Yes' : 'Off',
            'Skills Needed in the Workforce for Specific Jobs'        => in_array('workforce_skills', $services) ? 'Yes' : 'Off',
            'Non-traditional Employment Options (military, entrepreneurship, and self-employment)' => in_array('non_traditional_employment', $services) ? 'Yes' : 'Off',
        ];

        return array_merge(
            $this->commonHeader($log, '122X Eligible Student', '1005X Potentially Eligible Student', true),
            $checkboxes,
            [
                'Individual Service' => $isGroup ? 'Off' : 'On',
                'Group Service'      => $isGroup ? 'On'  : 'Off',
                'Dates of Service Delivery'                  => $this->resolver->formatDate($log->service_date?->toDateString()),
                'Units of Service Utilized'                  => (string) ($log->units ?? ''),
                // Free-text narrative (long field name)
                'Career Speakers Career Student Organization Orientation and Registration with One Stop Career Center and Department of Labor services online Skills Needed in the Workforce for Specific Jobs Please provide a narrative describing the students experience with the Job Exploration Counseling services delivered'
                    => $custom['narrative'] ?? $log->notes ?? '',
            ],
        );
    }

    public function mapAll(int $clientId, string $type): array
    {
        return ServiceLog::where('client_id', $clientId)
            ->with(['client', 'authorization', 'user'])
            ->get()
            ->map(fn ($log) => $this->map($log, $type))
            ->toArray();
    }
}
