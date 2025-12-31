<?php

namespace App\Services;

class BarcodeGenerator
{
    public static function generate(string $template, array $data): string
    {
        $map = [
            '{company}'    => $data['company'] ?? '',
            '{location}'   => $data['location'] ?? '',
            '{logistics}'  => $data['logistics'] ?? '',
            '{po}'         => $data['po'] ?? '',
            '{department}' => $data['department'] ?? '',
            '{asset_id}'   => $data['asset_id'] ?? '',
            '{serial}'     => $data['serial'] ?? '',
            '{year}'       => date('Y'),
            '{month}'      => date('m'),
        ];

        return str_replace(
            array_keys($map),
            array_values($map),
            $template
        );
    }
}
