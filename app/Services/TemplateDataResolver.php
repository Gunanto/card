<?php

namespace App\Services;

use App\Models\GeneratedCard;
use App\Models\MediaAsset;
use App\Support\CardTemplateConfigSchema;
use Illuminate\Support\Arr;

class TemplateDataResolver
{
    /** @var array<int, array<string, mixed>> */
    protected array $payloadCache = [];

    public function payload(GeneratedCard $generatedCard): array
    {
        $cacheKey = (int) $generatedCard->id;
        if (isset($this->payloadCache[$cacheKey])) {
            return $this->payloadCache[$cacheKey];
        }

        $student = $generatedCard->student;
        $institution = $generatedCard->batch->institution;

        $payload = [
            'student' => [
                'name' => (string) ($student?->name ?? ''),
                'student_code' => (string) ($student?->student_code ?? ''),
                'exam_number' => (string) ($student?->exam_number ?? ''),
                'class_name' => (string) ($student?->classroom?->name ?? ''),
                'classroom_name' => (string) ($student?->classroom?->name ?? ''),
                'school_name' => (string) ($student?->school_name ?? ''),
            ],
            'institution' => [
                'name' => (string) ($institution?->name ?? ''),
                'address' => (string) ($institution?->address ?? ''),
                'phone' => (string) ($institution?->phone ?? ''),
                'email' => (string) ($institution?->email ?? ''),
                'leader_name' => (string) ($institution?->leader_name ?? ''),
                'leader_nip' => (string) data_get($institution, 'leader_nip', ''),
                'leader_title' => (string) ($institution?->leader_title ?? ''),
            ],
        ];

        $this->payloadCache[$cacheKey] = $payload;

        return $payload;
    }

    public function resolveTextValue(array $element, GeneratedCard $generatedCard): string
    {
        $mode = (string) ($element['mode'] ?? 'dynamic');

        if ($mode === 'static') {
            return trim((string) ($element['text'] ?? ''));
        }

        $source = $this->normalizeSource((string) ($element['source'] ?? ''), (string) ($element['key'] ?? ''));
        if ($source === '') {
            return '';
        }

        $value = Arr::get($this->payload($generatedCard), $source);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    public function resolveImageAsset(array $element, array $assetMap): ?MediaAsset
    {
        $source = $this->normalizeSource((string) ($element['source'] ?? ''), (string) ($element['key'] ?? ''));
        $assetKey = $this->assetKeyForSource($source);

        if ($assetKey === null) {
            return null;
        }

        $asset = $assetMap[$assetKey] ?? null;

        return $asset instanceof MediaAsset ? $asset : null;
    }

    protected function normalizeSource(string $source, string $legacyKey): string
    {
        $source = trim($source);
        if ($source !== '') {
            if (str_starts_with($source, 'legacy.')) {
                $rawKey = trim(substr($source, 7));

                return CardTemplateConfigSchema::SOURCE_BY_LEGACY_KEY[$rawKey] ?? '';
            }

            return $source;
        }

        $legacyKey = trim($legacyKey);
        if ($legacyKey === '') {
            return '';
        }

        return CardTemplateConfigSchema::SOURCE_BY_LEGACY_KEY[$legacyKey] ?? '';
    }

    protected function assetKeyForSource(string $source): ?string
    {
        return match ($source) {
            'media.student_photo' => 'student_photo',
            'media.institution_logo' => 'institution_logo',
            'media.institution_stamp' => 'institution_stamp',
            'media.leader_signature' => 'leader_signature',
            default => null,
        };
    }
}
