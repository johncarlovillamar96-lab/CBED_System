<?php

// Run: php tests/mating-calf-reports.php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Carbon::setTestNow('2026-11-17');

use App\Support\MatingCalfReports;

$record = fn ($id, $dam, $service, $pd, $result = 'Positive') => (object) [
    'id' => $id, 'carabao_id' => $dam, 'mating_date' => $service,
    'pregnancy_check_date' => $pd, 'pregnancy_result' => $result,
    'actual_calving_date' => '2021-01-01',
];
$records = collect([
    $record(1, 1, '2020-01-01', '2020-03-01'),
    $record(2, 1, '2024-01-01', '2024-03-01'),
    $record(3, 2, '2020-01-01', '2020-03-01'),
    $record(4, 3, null, '2024-03-01', '+'),
    $record(5, 4, '2024-01-01', '2024-03-01', 'Negative'),
    $record(6, 5, '2024-01-01', '2024-03-01'),
    $record(7, 1, '2024-01-01', '2024-04-01'),
]);
$before = $records->map(fn ($r) => (array) $r)->all();
$calves = collect([
    (object) ['dam_id' => 1, 'birth_date' => '2024-11-01'],
    (object) ['dam_id' => 3, 'birth_date' => '2024-11-01'],
    (object) ['dam_id' => 5, 'birth_date' => '2023-11-01'],
]);
$result = MatingCalfReports::annotate($records, $calves)->keyBy('id');
$expected = [1 => false, 2 => true, 3 => false, 4 => true, 5 => false, 6 => false, 7 => true];
if ($result->count() !== count($before)) throw new RuntimeException('Mating history was removed');
foreach ($before as $original) {
    $row = $result[$original['id']];
    if ($row->calf_drop_reported !== $expected[$row->id]) throw new RuntimeException('Wrong calf-report match: '.$row->id);
    foreach ($original as $field => $value) if ($row->$field !== $value) throw new RuntimeException('Original field changed: '.$field);
}
$boundary = collect([
    $record(8, 8, \Illuminate\Support\Carbon::today()->subDays(300)->format('Y-m-d'), null),
    $record(9, 9, \Illuminate\Support\Carbon::today()->subDays(301)->format('Y-m-d'), null),
    $record(10, 10, '2020-01-01', null),
    $record(11, 11, null, null),
]);
foreach ($boundary as $row) $row->registry_number = 'ANIMAL-'.$row->carabao_id;
$boundary = MatingCalfReports::annotate($boundary, collect(), collect([
    (object) ['mother_id' => 'animal-8', 'birth_date' => '2026-11-01'],
    (object) ['mother_id' => ' ANIMAL-9 ', 'birth_date' => '2026-11-01'],
    (object) ['mother_id' => 'ANIMAL-11', 'birth_date' => '2026-11-01'],
    (object) ['mother_id' => 'unknown', 'birth_date' => '2026-11-01'],
]))->keyBy('id');
if ($boundary[8]->pregnancy_followup_complete || ! $boundary[9]->pregnancy_followup_complete
    || $boundary[10]->pregnancy_followup_complete || $boundary[11]->pregnancy_followup_complete) throw new RuntimeException('300-day threshold or Mother ID matching failed');
$updated = $record(12, 12, '2025-11-23', '2026-08-18');
$updated->registry_number = '5PAN23277';
$olderCalf = collect([(object) ['mother_id' => '5PAN23277', 'birth_date' => '2025-09-21']]);
$checked = MatingCalfReports::annotate(collect([$updated]), collect(), $olderCalf)->first();
if ($checked->pregnancy_followup_complete || $checked->calf_drop_reported) throw new RuntimeException('Older calf hid the corrected 5PAN23277 mating');
$updated->mating_date = '2024-11-07';
if (! MatingCalfReports::annotate(collect([$updated]), collect(), $olderCalf)->first()->pregnancy_followup_complete) throw new RuntimeException('Earlier completed pregnancy was not recognized');
\Illuminate\Support\Carbon::setTestNow();
echo "PASS: Updated mating dates stay visible when the calf is older; matching births, pregnancy boundaries, 300-day threshold and unchanged history verified.\n";
