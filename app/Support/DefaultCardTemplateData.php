<?php

namespace App\Support;

class DefaultCardTemplateData
{
    public static function config(): array
    {
        return [
            'canvas' => [
                'width_mm' => 85.6,
                'height_mm' => 54,
            ],
            'elements' => [
                ['type' => 'photo', 'key' => 'student_photo', 'x' => 6, 'y' => 10, 'w' => 20, 'h' => 26, 'z' => 10],
                ['type' => 'text', 'key' => 'name', 'x' => 30, 'y' => 14, 'font_size' => 10, 'font_weight' => '700', 'z' => 20],
                ['type' => 'text', 'key' => 'student_code', 'x' => 30, 'y' => 20, 'font_size' => 8, 'z' => 20],
                ['type' => 'image', 'key' => 'institution_logo', 'x' => 6, 'y' => 4, 'w' => 10, 'h' => 10, 'z' => 30],
                ['type' => 'image', 'key' => 'institution_stamp', 'x' => 58, 'y' => 26, 'w' => 20, 'h' => 20, 'opacity' => 0.55, 'z' => 40],
                ['type' => 'image', 'key' => 'leader_signature', 'x' => 58, 'y' => 40, 'w' => 20, 'h' => 8, 'z' => 50],
                ['type' => 'text', 'key' => 'leader_name', 'x' => 58, 'y' => 49, 'font_size' => 6, 'z' => 60],
                ['type' => 'text', 'key' => 'leader_title', 'x' => 58, 'y' => 52, 'font_size' => 5, 'z' => 60],
            ],
        ];
    }

    public static function printLayout(): array
    {
        return [
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'grid' => [
                'columns' => 2,
                'rows' => 5,
            ],
            'card_size_mm' => [
                'width' => 85.6,
                'height' => 54,
            ],
            'gap_mm' => [
                'x' => 5,
                'y' => 5,
            ],
            'padding_mm' => [
                'top' => 9.5,
                'right' => 16.9,
                'bottom' => 9.5,
                'left' => 16.9,
            ],
            'print_margin_mode' => 'none',
        ];
    }

    public static function configJson(): string
    {
        return json_encode(self::config(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function printLayoutJson(): string
    {
        return json_encode(self::printLayout(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
