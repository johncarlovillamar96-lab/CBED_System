<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringReportDetail extends Model
{
    protected $fillable = [
        'monitoring_report_id', 'carabao_id', 'animal_database_id', 'bolus_id', 'registry_number', 'tag_number',
        'animal_name', 'farmer_name', 'sex', 'birth_date', 'breed', 'availability_status', 'unavailability_reason',
        'monitoring_specification', 'destination_module', 'destination_record_id', 'destination_inventory_id',
        'monitoring_status', 'monitoring_data', 'remarks',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'monitoring_data' => 'array'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonitoringReport::class, 'monitoring_report_id');
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Carabao::class, 'carabao_id');
    }
}
