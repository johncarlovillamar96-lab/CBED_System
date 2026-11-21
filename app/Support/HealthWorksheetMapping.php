<?php

namespace App\Support;

class HealthWorksheetMapping
{
    public static function isBolusService(string $name): bool
    {
        return in_array(preg_replace('/[^a-z0-9]/', '', strtolower($name)), ['bolus', 'bolusapplication'], true);
    }

    public const ADDITIONAL_SERVICES = [
        'fecalysis' => 'Fecalysis',
        'recruitment' => 'Recruitment',
        'hands_on_milking' => 'Hands-on Milking',
        'forage_demo' => 'Forage Demo',
    ];

    private const FECALYSIS_HEADERS = ['Fecalysis', 'Fecalysis (FS)', 'No. of Fecalysis', 'FS'];

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
        ...self::ADDITIONAL_SERVICES,
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

        $fecalysisHeaders = array_map(self::normalize(...), self::FECALYSIS_HEADERS);
        foreach ($headers as $column => $header) {
            if (in_array(self::normalize((string) $header), $fecalysisHeaders, true)) {
                $values['fecalysis'] = $row[$column] ?? null;
                break;
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

        foreach (['fecalysis' => [null, self::FECALYSIS_HEADERS], ...self::COLUMNS] as $field => [$column, $labels]) {
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

    public static function additionalServiceValues(array $source, array $worksheet, array $services): array
    {
        $keys = array_flip(array_map(self::normalize(...), self::ADDITIONAL_SERVICES));
        $keys['fs'] = $keys['fecalysisfs'] = 'fecalysis';
        $keys['handonmilking'] = 'hands_on_milking';
        $counts = [];
        foreach ($services as $service) {
            $name = $service['type'] === 'Other Services' ? $service['other_service_name'] : $service['type'];
            $field = $keys[self::normalize($name)] ?? null;
            if ($field !== null) {
                $counts[$field] = ($counts[$field] ?? 0) + 1;
            }
        }

        $values = [];
        foreach (self::ADDITIONAL_SERVICES as $field => $label) {
            $value = $worksheet[$field] ?? $source['monitoring_fields'][$field] ?? $source[$field] ?? null;
            $values[$field] = $counts[$field] ?? ($value === '' ? null : $value);
        }

        return $values;
    }

    public static function serviceIndicators(array $source, array $worksheet, array $services): array
    {
        $values = self::additionalServiceValues($source, $worksheet, $services);
        $aliases = [
            'deworming' => ['deworm', 'deworming', 'dewormingservices'],
            'vitamins_minerals' => ['vitamins', 'vitaminsmineralsupplements', 'vitaminsmineralssupplementation', 'vitaminsupplementation'],
            'vaccination' => ['vaccination', 'vaccinations', 'vaccine'],
            'uterine_flushing' => ['uterineflushing', 'uterinflushing', 'flushing'],
            'ear_tagging' => ['eartagging', 'eartagged'],
        ];
        $names = array_map(fn ($service) => self::normalize(
            $service['type'] === 'Other Services' ? $service['other_service_name'] : $service['type']
        ), $services);

        foreach ($aliases as $field => $serviceNames) {
            $values[$field] = array_intersect($names, $serviceNames) ? 1 : ($worksheet[$field] ?? null);
        }

        return array_map(static function ($value) {
            if (is_numeric($value)) {
                return (float) $value > 0 ? 1 : null;
            }

            return in_array(strtolower(trim((string) $value)), ['', '-', '—', 'no', 'false', 'none', 'n/a', 'na'], true) ? null : 1;
        }, $values);
    }

    private static function normalize(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $value));
    }
}
