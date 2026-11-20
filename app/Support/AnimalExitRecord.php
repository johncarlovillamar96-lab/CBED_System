<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class AnimalExitRecord
{
    public const DETAILS = ['reason_of_exit', 'entrustment', 'ownership_status', 'disposed_to', 'disposal_province', 'disposal_municipality', 'disposal_barangay'];
    public const LABELS = [
        'exit_date' => 'Exit Date', 'nature_of_exit' => 'Nature of Exit',
        'reason_of_exit' => 'Reason for Exit', 'entrustment' => 'Type of Entrustment',
        'ownership_status' => 'Ownership Status', 'disposed_to' => 'Sold, Transferred, or Disposed To',
        'disposal_province' => 'Province', 'disposal_municipality' => 'Municipality', 'disposal_barangay' => 'Barangay',
        'remarks' => 'Remarks',
    ];

    public static function rules(): array
    {
        return [
            'exit_date' => ['required', 'date_format:Y-m-d'],
            'nature_of_exit' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ] + array_fill_keys(self::DETAILS, ['nullable', 'string', 'max:255']);
    }

    public static function save(int $animalId, array $data, array $identity, ?int $recordId = null): ?int
    {
        $fields = array_intersect_key($data, self::rules()) + ['details_entered' => true, 'updated_at' => now()];

        return DB::transaction(function () use ($animalId, $fields, $identity, $recordId) {
            DB::table('carabaos')->where('id', $animalId)->lockForUpdate()->first(['id']);
            $records = DB::table('nature_of_exits')->where('carabao_id', $animalId);
            $duplicate = (clone $records)->where('exit_date', $fields['exit_date'])
                ->where('nature_of_exit', $fields['nature_of_exit'])
                ->when($recordId, fn ($query) => $query->where('id', '!=', $recordId))
                ->lockForUpdate()->first(['id']);
            if ($duplicate) return null;

            if ($recordId) {
                abort_unless($records->where('id', $recordId)->lockForUpdate()->first(['id']), 404, 'Exit record was not found.');
                $records->update($fields);
                return $recordId;
            }

            return DB::table('nature_of_exits')->insertGetId($fields + $identity + [
                'carabao_id' => $animalId, 'created_at' => now(),
            ]);
        });
    }
}
