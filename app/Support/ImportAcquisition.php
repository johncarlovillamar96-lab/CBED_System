<?php

namespace App\Support;

class ImportAcquisition
{
    public const FIELDS = ['acquisition_date', 'acquisition_cost'];
    public const DATE_HEADERS = ['date of acquisition', 'acquisition date'];
    public const COST_HEADERS = ['acquisition cost', 'aquisition cost'];

    public static function cost(mixed $value): ?string
    {
        $amount = str_replace(',', '', trim((string) $value));
        return is_numeric($amount) ? $amount : null;
    }

    public static function updates(array $incoming, object $farmer): array
    {
        $date = $incoming['acquisition_date'];
        $cost = $incoming['acquisition_cost'];

        if ($date !== null && ($farmer->acquisition_date === null || $date > $farmer->acquisition_date)) {
            return $incoming;
        }

        if ($date === $farmer->acquisition_date && $cost !== null
            && ($farmer->acquisition_cost === null || (float) $cost !== (float) $farmer->acquisition_cost)) {
            return ['acquisition_cost' => $cost];
        }

        return [];
    }
}
