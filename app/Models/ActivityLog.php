<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ActivityLog extends Model
{
    public const TIMEZONE = 'Asia/Manila';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['details' => 'array', 'occurred_at' => 'datetime'];
    }

    public function scopeRecordedTransactions(Builder $query): Builder
    {
        return $query->whereNotNull('description');
    }

    public function localTime(): Carbon
    {
        return $this->occurred_at->copy()->timezone(self::TIMEZONE);
    }

    public function activityDescription(): string
    {
        if ($this->description === null) {
            return 'Historical activity. Saved entry details are unavailable.';
        }

        $details = $this->details ?? [];
        $context = [];
        if ($this->module === 'Animal Monitoring' && !empty($details['transaction_dates'])) {
            $context[] = 'Monitoring date: '.implode(', ', $details['transaction_dates']);
        }
        if (!empty($details['animal_ids'])) {
            $ids = $details['animal_ids'];
            $visibleIds = array_slice($ids, 0, 5);
            $remaining = count($ids) - count($visibleIds);
            $context[] = 'Animal IDs: '.implode(', ', $visibleIds).($remaining > 0 ? " (+$remaining more)" : '');
        }
        if (!empty($details['services'])) {
            $services = [];
            foreach ($details['services'] as $service => $count) {
                $services[] = "$service ($count)";
            }
            $context[] = 'Recorded services and results: '.implode(', ', $services);
        }

        return implode("\n", [$this->description, ...$context]);
    }
}
