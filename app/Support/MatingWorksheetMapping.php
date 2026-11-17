<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class MatingWorksheetMapping
{
    public static function matchesHeaders(array $headers, string $sheetKey): bool
    {
        $index = $sheetKey === 'mating_2' ? 5 : 4;
        return strtolower(trim((string) ($headers[$index] ?? ''))) === 'date of ai';
    }

    public static function fromRow(array $row, string $sheetKey): array
    {
        // Mating (2) has extra tag columns and no method column at S.
        $second = $sheetKey === 'mating_2';
        $aiAnimal = $row[$second ? 4 : 3] ?? null;
        $aiDate = $row[$second ? 5 : 4] ?? null;
        $naturalAnimal = $row[$second ? 10 : 8] ?? null;
        $naturalDate = $row[$second ? 11 : 9] ?? null;
        $type = $second ? null : ($row[18] ?? null);
        if (trim((string) $type) === '') {
            $type = self::date($aiDate) ? 'AI' : (self::date($naturalDate) ? 'NM' : null);
        }
        $method = self::method($type);

        return [...self::mapped(
            self::text($aiAnimal) ?? ($method === 'NM' ? self::text($naturalAnimal) : null),
            self::date($aiDate) ?? ($method === 'NM' ? self::date($naturalDate) : null),
            $method,
            $row[$method === 'NM' ? ($second ? 12 : 10) : ($second ? 6 : 5)] ?? null,
            $row[$second ? 7 : 6] ?? null,
            $row[$second ? 15 : 13] ?? null,
            $row[$second ? 16 : 14] ?? null
        ), 'pregnancy_months' => self::months($row[$second ? 17 : 15] ?? null),
            'pd_technician' => self::text($row[$second ? 18 : 16] ?? null)];
    }

    public static function fromSource(array $source): ?array
    {
        if (! array_key_exists('Date of AI', $source) && ! array_key_exists('Bull in date', $source)
            && ! array_key_exists('Actual Date of PD', $source)) return null;
        if (isset($source['mating_column_mapping'])) return $source['mating_column_mapping']
            + ['pregnancy_months' => self::pregnancyMonthsFromSource($source), 'pd_technician' => self::pdTechnicianFromSource($source)];

        $method = self::method($source[''] ?? $source['Type of mating'] ?? null);
        if ($method === null) {
            $method = self::date($source['Date of AI'] ?? null) ? 'AI'
                : (self::date($source['Bull in date'] ?? null) ? 'NM' : null);
        }
        // Legacy imports retain duplicate headers with their zero-based column index.
        $bull = $method === 'NM'
            ? ($source['Bull ID no. [10]'] ?? $source['Bull ID no. [12]'] ?? $source['Bull ID no.'] ?? null)
            : ($source['Bull ID no.'] ?? null);

        return [...self::mapped(
            $source['Register No.'] ?? $source['Cow Registry Number'] ?? null,
            self::date($source['Date of AI'] ?? null) ?? ($method === 'NM' ? self::date($source['Bull in date'] ?? null) : null),
            $method, $bull, $source['Type of estrus'] ?? null,
            $source['Actual Date of PD'] ?? null, $source['Result'] ?? null
        ), 'pregnancy_months' => self::pregnancyMonthsFromSource($source), 'pd_technician' => self::pdTechnicianFromSource($source)];
    }

    public static function pdTechnicianFromSource(array $source): ?string
    {
        return self::text($source['Confirmed by'] ?? $source['pd_technician']
            ?? $source['mating_column_mapping']['pd_technician'] ?? null);
    }

    public static function pregnancyMonthsFromSource(array $source): ?int
    {
        return self::months($source['Mos. Pregnant'] ?? $source['pregnancy_months']
            ?? $source['mating_column_mapping']['pregnancy_months'] ?? null);
    }

    private static function months(mixed $value): ?int
    {
        return is_numeric($value) && (float) $value === floor((float) $value)
            && $value >= 0 && $value <= 11 ? (int) $value : null;
    }

    private static function mapped(?string $animal, ?string $date, ?string $method, mixed $bull, mixed $heat, mixed $pdDate, mixed $pdResult): array
    {
        $result = self::text($pdResult);
        $result = match (strtolower($result ?? '')) {
            '+', 'p', 'positive', 'pregnant', 'yes' => 'Positive',
            '-', 'n', 'negative', 'not pregnant', 'non-pregnant', 'no' => 'Negative',
            '', 'pending', 'null', 'n/a', 'na', '?' => 'Pending',
            default => $result,
        };

        return ['registry_number' => self::text($animal), 'mating_date' => $date, 'mating_type' => $method,
            'bull_id' => self::text($bull), 'pregnancy_check_date' => self::date($pdDate),
            'pregnancy_result' => $result, 'estrus_type' => $method === 'AI' ? self::text($heat) : null];
    }

    private static function method(mixed $value): ?string
    {
        return match (strtoupper(trim((string) $value))) {
            'AI', 'ARTIFICIAL INSEMINATION' => 'AI',
            'N', 'NM', 'NS', 'NATURAL', 'NATURAL MATING', 'NATURAL SERVICE' => 'NM',
            default => null,
        };
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function date(mixed $value): ?string
    {
        if (self::text($value) === null) return null;
        try {
            if (is_numeric($value)) {
                return $value > 20000 && $value < 80000 ? Date::excelToDateTimeObject((float) $value)->format('Y-m-d') : null;
            }
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) { return null; }
    }
}
