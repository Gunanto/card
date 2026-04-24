<?php

namespace App\Support;

class SimplePdf
{
    public static function fromLines(array $lines): string
    {
        $y = 790;
        $commands = ['BT', '/F1 12 Tf'];

        foreach ($lines as $index => $line) {
            $escaped = self::escape((string) $line);
            $commands[] = sprintf('1 0 0 1 48 %d Tm (%s) Tj', $y - ($index * 18), $escaped);
        }

        $commands[] = 'ET';

        $stream = implode("\n", $commands);

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($stream), $stream),
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $index + 1, $object);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= sprintf("0 %d\n", count($objects) + 1);
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= sprintf(
            "trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF",
            count($objects) + 1,
            $xrefOffset,
        );

        return $pdf;
    }

    protected static function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $value);
    }
}
