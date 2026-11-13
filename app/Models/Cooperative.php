<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cooperative extends Model
{
    protected $table = 'cooperatives';

    public function animals(): HasMany
    {
        return $this->hasMany(Carabao::class, 'cooperative_id');
    }

    public function farmers(): HasMany
    {
        return $this->hasMany(Farmer::class, 'cooperative_id');
    }

    public function monitoringReports(): HasMany
    {
        return $this->hasMany(MonitoringReport::class, 'cooperative_id');
    }
}
