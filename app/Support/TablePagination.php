<?php

namespace App\Support;

use Illuminate\Http\Request;

class TablePagination
{
    public const DEFAULT_SIZE = 15;
    public const SIZES = [10, 15, 25, 50, 100];

    public static function size(Request $request, string $parameter = 'per_page'): int
    {
        $value = $request->query($parameter);

        return in_array($value, array_map('strval', self::SIZES), true) ? (int) $value : self::DEFAULT_SIZE;
    }
}
