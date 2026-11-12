<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpreadsheetWorksheetReader
{
    public static function rows(Worksheet $worksheet, int $startRow = 1, ?int $endRow = null): array
    {
        $lastRow = $worksheet->getHighestDataRow();
        $lastColumn = $worksheet->getHighestDataColumn();
        $endRow = min($lastRow, $endRow ?? $lastRow);
        $startRow = max(1, $startRow);
        if ($lastRow < $startRow || $endRow < $startRow || ($lastColumn === 'A' && $worksheet->getCell('A'.$startRow)->getValue() === null)) {
            return [];
        }

        $columnCount = Coordinate::columnIndexFromString($lastColumn);
        $rows = [];
        for ($rowIndex = $startRow; $rowIndex <= $endRow; $rowIndex++) {
            $values = [];
            for ($columnIndex = 1; $columnIndex <= $columnCount; $columnIndex++) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex).$rowIndex;
                $cell = $worksheet->getCell($coordinate);
                $value = $cell->getValue();
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    $value = $cell->getOldCalculatedValue();
                }
                if (is_string($value) && preg_match('/^#(?:REF!|VALUE!|N\/A|NAME\?|DIV\/0!|NUM!|NULL!)/i', trim($value))) {
                    $value = null;
                }
                $values[] = $value;
            }
            $rows[] = $values;
        }

        return $rows;
    }
}
