<?php

// Run: php tests/fhpr-classification.php
require dirname(__DIR__).'/vendor/autoload.php';

use App\Support\FhprClassification;

$cases = [
    ['Heifer', null, null, 'Positive', 'HeiferPregnant'],
    ['Heifer', 200, null, '+', 'HeiferPregnant'],
    ['CowLactating', null, null, 'Positive', 'HeiferPregnant'],
    ['CowLactating', 474, null, 'Positive', 'CowLactatingPregnant'],
    ['CowLactating', 304, '2026-05-31', 'Positive', 'CowDryPregnant'],
    ['CowDry', null, '2026-05-31', '+', 'CowDryPregnant'],
    ['CowLactatingPregnant', 474, null, 'Negative', 'CowLactating'],
    ['CowDryPregnant', 304, '2026-05-31', '-', 'CowDry'],
    ['CowDryPregnant', 474, null, 'Positive', 'CowLactatingPregnant'],
    ['HeiferPregnant', null, null, 'Negative', 'Heifer'],
    ['CowDry', 304, '2026-05-31', null, 'CowDry'],
    ['Yearling Heifer', null, null, null, 'Yearling Heifer'],
    [null, 300, '2026-05-31', 'Positive', null],
];
foreach ($cases as [$saved, $days, $dry, $pd, $expected]) {
    $actual = FhprClassification::resolve($saved, $days, $dry, $pd);
    if ($actual !== $expected) throw new RuntimeException('Expected '.$expected.', got '.$actual);
}
echo "PASS: Heifer, lactating, dry, positive, negative, cleared dry-off and blank classification rules.\n";
