<?php

namespace App\Support;

class FarmerImportAddress
{
    public const HEADERS = [
        'barangay' => ['barangay', 'brgy'],
        'municipality' => ['municipality', 'city/municipality'],
        'province' => ['province'],
        'district' => ['district'],
        'region' => ['region'],
    ];

    public static function fromHeaders(callable $get): array
    {
        $address = [];
        foreach (self::HEADERS as $field => $headers) {
            $value = trim((string) $get($headers));
            $address[$field] = $value === '' ? null : $value;
        }

        if (preg_match('/^(?:region\s+)?(III)$/i', $address['region'] ?? '')) {
            $address['region'] = 'III';
        }

        return $address;
    }
}
