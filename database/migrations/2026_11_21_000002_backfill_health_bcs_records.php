<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('health_records')->whereNotNull('source_data')->whereNotNull('carabao_id')
            ->whereNotNull('monitoring_date')->chunkById(200, function ($activities) {
                foreach ($activities as $activity) {
                    $source = json_decode($activity->source_data, true);
                    $score = $source['body_condition_score'] ?? null;
                    if (! is_numeric($score) || $score < 0 || $score > 10) {
                        continue;
                    }
                    $detailId = DB::table('monitoring_report_details')->where('destination_module', 'Health & Nutrition')
                        ->where('destination_record_id', $activity->id)->value('id');
                    if ($detailId && DB::table('bcs_records')->where('monitoring_report_detail_id', $detailId)
                        ->where('carabao_id', $activity->carabao_id)->where('record_date', $activity->monitoring_date)
                        ->where('body_condition_score', $score)->exists()) {
                        continue;
                    }
                    DB::table('bcs_records')->insertOrIgnore([
                        'health_record_id' => $activity->id,
                        'carabao_id' => $activity->carabao_id,
                        'cooperative_id' => $activity->cooperative_id,
                        'registry_number' => $activity->registry_number,
                        'record_date' => $activity->monitoring_date,
                        'body_condition_score' => $score,
                        'created_at' => $activity->created_at ?? now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Retain BCS history, including records edited after the backfill.
    }
};
