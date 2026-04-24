<?php

namespace App\Support;

class CardTemplateConfigSchema
{
    public const VERSION = 2;

    /**
     * Canonical data source -> legacy renderer key.
     * Keep this map centralized so renderer/editor migration stays maintainable.
     */
    public const LEGACY_KEY_BY_SOURCE = [
        'student.name' => 'student_name',
        'student.student_code' => 'student_code',
        'student.exam_number' => 'exam_number',
        'student.classroom_name' => 'classroom_name',
        'student.school_name' => 'school_name',
        'institution.name' => 'institution_name',
        'institution.address' => 'institution_address',
        'institution.leader_name' => 'leader_name',
        'institution.leader_nip' => 'leader_nip',
        'institution.leader_title' => 'leader_title',
        'media.student_photo' => 'student_photo',
        'media.institution_logo' => 'institution_logo',
        'media.institution_stamp' => 'institution_stamp',
        'media.leader_signature' => 'leader_signature',
    ];

    public const SOURCE_BY_LEGACY_KEY = [
        'name' => 'student.name',
        'student_name' => 'student.name',
        'student_code' => 'student.student_code',
        'exam_number' => 'student.exam_number',
        'class_name' => 'student.classroom_name',
        'classroom_name' => 'student.classroom_name',
        'school_name' => 'student.school_name',
        'institution_name' => 'institution.name',
        'institution_address' => 'institution.address',
        'address' => 'institution.address',
        'leader_name' => 'institution.leader_name',
        'leader_nip' => 'institution.leader_nip',
        'leader_title' => 'institution.leader_title',
        'student_photo' => 'media.student_photo',
        'institution_logo' => 'media.institution_logo',
        'institution_stamp' => 'media.institution_stamp',
        'leader_signature' => 'media.leader_signature',
    ];

    public static function normalize(array $rawConfig, float $defaultCanvasWidthMm = 85.6, float $defaultCanvasHeightMm = 54): array
    {
        $rawElements = is_array($rawConfig['elements'] ?? null) ? $rawConfig['elements'] : [];
        $elements = [];

        foreach ($rawElements as $index => $rawElement) {
            if (! is_array($rawElement)) {
                continue;
            }

            $elements[] = self::normalizeElement($rawElement, (int) $index);
        }

        if ($elements === []) {
            $elements = DefaultCardTemplateData::config()['elements'];
        }

        return [
            'schema_version' => self::VERSION,
            'canvas' => [
                'width_mm' => self::number($rawConfig['canvas']['width_mm'] ?? null, $defaultCanvasWidthMm),
                'height_mm' => self::number($rawConfig['canvas']['height_mm'] ?? null, $defaultCanvasHeightMm),
            ],
            'elements' => array_values($elements),
        ];
    }

    public static function validate(array $config): array
    {
        $errors = [];
        $elements = is_array($config['elements'] ?? null) ? $config['elements'] : [];

        if ($elements === []) {
            $errors[] = 'Config JSON harus memiliki elemen template.';

            return $errors;
        }

        foreach ($elements as $index => $element) {
            if (! is_array($element)) {
                $errors[] = sprintf('Elemen #%d tidak valid.', $index + 1);
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            if (! in_array($type, ['text', 'image', 'photo'], true)) {
                $errors[] = sprintf('Elemen #%d memiliki type tidak valid.', $index + 1);
                continue;
            }

            if ($type === 'text') {
                $mode = (string) ($element['mode'] ?? '');
                if (! in_array($mode, ['dynamic', 'static'], true)) {
                    $errors[] = sprintf('Elemen text #%d wajib punya mode `dynamic` atau `static`.', $index + 1);
                    continue;
                }

                if ($mode === 'dynamic' && trim((string) ($element['source'] ?? '')) === '') {
                    $errors[] = sprintf('Elemen text dynamic #%d wajib punya `source`.', $index + 1);
                }

                if ($mode === 'static' && trim((string) ($element['text'] ?? '')) === '') {
                    $errors[] = sprintf('Elemen text static #%d wajib punya `text`.', $index + 1);
                }
            } else {
                if (trim((string) ($element['source'] ?? '')) === '') {
                    $errors[] = sprintf('Elemen %s #%d wajib punya `source`.', $type, $index + 1);
                }
            }
        }

        return $errors;
    }

    protected static function normalizeElement(array $rawElement, int $index): array
    {
        $type = in_array(($rawElement['type'] ?? null), ['text', 'image', 'photo'], true)
            ? (string) $rawElement['type']
            : 'text';

        $source = self::normalizeSource($rawElement);
        $legacyKey = self::normalizeLegacyKey($rawElement, $source, $index);

        $element = [
            'type' => $type,
            // Keep legacy key for current renderer compatibility.
            'key' => $legacyKey,
            'source' => $source,
            'x' => self::number($rawElement['x'] ?? $rawElement['x_mm'] ?? null, 0),
            'y' => self::number($rawElement['y'] ?? $rawElement['y_mm'] ?? null, 0),
            'w' => self::number($rawElement['w'] ?? $rawElement['w_mm'] ?? null, $type === 'text' ? 0 : 20),
            'h' => self::number($rawElement['h'] ?? $rawElement['h_mm'] ?? null, $type === 'text' ? 0 : 10),
            'z' => (int) self::number($rawElement['z'] ?? $rawElement['z_index'] ?? null, ($index + 1) * 10),
            'opacity' => self::number($rawElement['opacity'] ?? null, 1),
        ];

        if ($type === 'text') {
            $mode = (string) ($rawElement['mode'] ?? '');
            if (! in_array($mode, ['dynamic', 'static'], true)) {
                $mode = trim((string) ($rawElement['text'] ?? '')) !== '' ? 'static' : 'dynamic';
            }

            $element['mode'] = $mode;
            $element['text'] = trim((string) ($rawElement['text'] ?? ''));
            $element['font_size'] = self::number($rawElement['font_size'] ?? $rawElement['font_size_mm'] ?? null, 2.8);
            $element['font_weight'] = trim((string) ($rawElement['font_weight'] ?? '400')) ?: '400';
            $element['color'] = trim((string) ($rawElement['color'] ?? '#111827')) ?: '#111827';
        }

        return $element;
    }

    protected static function normalizeSource(array $rawElement): string
    {
        $source = trim((string) ($rawElement['source'] ?? ''));
        if ($source !== '') {
            return $source;
        }

        $legacyKey = trim((string) ($rawElement['key'] ?? ''));
        if ($legacyKey === '') {
            return '';
        }

        return self::SOURCE_BY_LEGACY_KEY[$legacyKey] ?? 'legacy.'.$legacyKey;
    }

    protected static function normalizeLegacyKey(array $rawElement, string $source, int $index): string
    {
        $legacyKey = trim((string) ($rawElement['key'] ?? ''));
        if ($legacyKey !== '') {
            return $legacyKey;
        }

        if ($source !== '' && isset(self::LEGACY_KEY_BY_SOURCE[$source])) {
            return self::LEGACY_KEY_BY_SOURCE[$source];
        }

        if (str_starts_with($source, 'legacy.')) {
            $legacyFromSource = trim(substr($source, 7));
            if ($legacyFromSource !== '') {
                return $legacyFromSource;
            }
        }

        return sprintf('element_%d', $index + 1);
    }

    protected static function number(mixed $value, float $default): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }
}
