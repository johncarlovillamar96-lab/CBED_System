<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringReport extends Model
{
    protected $fillable = [
        'cooperative_id', 'created_by', 'cooperative_name', 'province', 'municipality', 'barangay',
        'report_date', 'monitored_by', 'attested_by', 'noted_by', 'report_data', 'remarks',
    ];

    protected function casts(): array
    {
        return ['report_date' => 'date', 'report_data' => 'array'];
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(MonitoringReportDetail::class);
    }
}
