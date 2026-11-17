<?php

namespace App\Support;

class InventoryClassification
{
    public static function isExcluded(?string $status, ?string $natureOfExit = null): bool
    {
        foreach ([$status, $natureOfExit] as $value) {
            $value = strtolower(trim((string) $value));
            if ($value === 'dead' || str_starts_with($value, 'cull') || str_starts_with($value, 'transf')) return true;
        }

        return false;
    }
}
