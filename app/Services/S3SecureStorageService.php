<?php

namespace App\Services;

use App\Models\GeneratedForm;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3SecureStorageService
{
    public function __construct(
        private readonly CryptographicAuditService $audit,
    ) {}

    public function uploadWithWorm(string $localPath, int $crpId, string $formType): string
    {
        if (! file_exists($localPath)) {
            throw new \RuntimeException("Local PDF not found: {$localPath}");
        }

        $date = now()->format('Y/m');
        $key  = "exports/{$crpId}/{$date}/" . Str::uuid()->toString() . '.pdf';

        $client = $this->client();

        $client->putObject([
            'Bucket'                    => config('filesystems.disks.s3.bucket'),
            'Key'                       => $key,
            'Body'                      => fopen($localPath, 'rb'),
            'ContentType'               => 'application/pdf',
            'ServerSideEncryption'      => 'AES256',
            'ObjectLockMode'            => 'COMPLIANCE',
            'ObjectLockRetainUntilDate' => now()->addYears(7)->toIso8601String(),
            'Metadata'                  => [
                'crp-id'    => (string) $crpId,
                'form-type' => $formType,
            ],
        ]);

        $this->audit->log(
            $crpId,
            auth()->id(),
            'phi_document_upload',
            'generated_form',
            null,
            [
                's3_key'              => $key,
                'form_type'           => $formType,
                'worm_retention_years'=> 7,
            ],
        );

        return $key;
    }

    public function presignedUrl(GeneratedForm $form, int $ttlMinutes = 15): string
    {
        $url = Storage::disk('s3')->temporaryUrl(
            $form->file_path,
            now()->addMinutes($ttlMinutes),
        );

        $this->audit->log(
            $form->crp_id,
            auth()->id(),
            'phi_document_download',
            'generated_form',
            $form->id,
            [
                's3_key'      => $form->file_path,
                'ttl_minutes' => $ttlMinutes,
                'form_type'   => $form->form_type,
            ],
        );

        return $url;
    }

    private function client(): S3Client
    {
        $cfg = config('filesystems.disks.s3');

        return new S3Client([
            'version'     => 'latest',
            'region'      => $cfg['region'],
            'credentials' => [
                'key'    => $cfg['key'],
                'secret' => $cfg['secret'],
            ],
        ]);
    }
}
