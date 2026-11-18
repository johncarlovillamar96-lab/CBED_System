<?php

namespace App\Support;

class HealthWorksheetMapping
{
    private const COLUMNS = [
        'dosage' => [10, ['Dosage (ml)', 'Dosage']],
        'deworming' => [11, ['No. of Deworm', 'Deworming']],
        'vitamins_minerals' => [12, ['No. of Vitamins / Minerals Supplementation', 'Vitamins / Mineral Supplements', 'Vitamins / Mineral Suppliments']],
        'vaccination' => [13, ['No. of Vaccination', 'Vaccination']],
        'uterine_flushing' => [14, ['No. of Uterine Flushing', 'Uterine Flushing', 'Uterin Flushing']],
        'artificial_insemination' => [15, ['No. of AI', 'Artificial Insemination']],
        'ear_tagging' => [16, ['No. of Ear Tagging', 'Ear Tagging']],
        'recruitment' => [17, ['No. of Recruitment', 'Recruitment']],
        'hands_on_milking' => [18, ['No. of Hands On Milking', 'Hands-on Milking']],
        'forage_demo' => [19, ['No. of Forage Demo', 'Forage Demo']],
        'no_of_visits_monitored' => [20, ['No. of Visit / Monitored', 'No. of Visits / Monitored']],
        'no_of_treatments' => [21, ['No. of Treatment', 'No. of Treatments']],
        'remarks' => [22, ['Remarks']],
    ];

    private const SERVICES = [
        'deworming' => 'Deworming',
        'vitamins_minerals' => 'Vitamins / Mineral Supplements',
        'vaccination' => 'Vaccination',
        'uterine_flushing' => 'Uterine Flushing',
        'artificial_insemination' => 'Artificial Insemination',
        'ear_tagging' => 'Ear Tagging',
        'recruitment' => 'Recruitment',
        'hands_on_milking' => 'Hands-on Milking',
        'forage_demo' => 'Forage Demo',
    ];

    public static function selectedServices(array $values): array
    {
        return array_values(array_filter(self::SERVICES, function ($field) use ($values) {
            $value = $values[$field] ?? null;
            return is_numeric($value) && (float) $value === 1.0;
        }, ARRAY_FILTER_USE_KEY));
    }

    public static function fromRow(array $headers, array $row): array
    {
        $values = [];
        foreach (self::COLUMNS as $field => [$column, $labels]) {
            if (in_array(self::normalize((string) ($headers[$column] ?? '')), array_map(self::normalize(...), $labels), true)) {
                $values[$field] = $row[$column] ?? null;
            }
        }

        return $values;
    }

    public static function fromSource(array $source): array
    {
        $values = is_array($source['health_worksheet'] ?? null) ? $source['health_worksheet'] : [];
        $headers = [];
        foreach ($source as $header => $value) {
            if (is_scalar($value)) $headers[self::normalize((string) $header)] = $value;
        }

        foreach (self::COLUMNS as $field => [$column, $labels]) {
            if (array_key_exists($field, $values)) continue;
            foreach ($labels as $label) {
                $key = self::normalize($label);
                if (array_key_exists($key, $headers)) {
                    $values[$field] = $headers[$key];
                    break;
                }
            }
        }

        return $values;
    }

    private static function normalize(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $value));
    }
}
