<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class ActivityAccomplishments
{
    public int $activities = 0;
    public int $created = 0;
    public int $updated = 0;
    public int $services = 0;
    public array $animals = [];
    public array $cooperatives = [];
    public array $breakdown = [];

    private array $countedServiceFacts = [];

    public function add(ActivityLog $entry): void
    {
        $this->activities++;
        $details = $entry->details ?? [];
        $this->created += $details['records_created'] ?? 0;
        $this->updated += $details['records_updated'] ?? 0;
        foreach ($details['cooperative_ids'] ?? [] as $id) {
            $this->cooperatives[$id] = true;
        }
        if (in_array($entry->module, ['Animal Monitoring', 'Health and Nutrition'], true) && $entry->action !== 'Delete') {
            foreach ($details['animal_ids'] ?? [] as $id) {
                $this->animals[mb_strtolower($id)] = true;
            }
        }
        if (isset($details['service_facts'])) {
            foreach ($details['service_facts'] as $key => $fact) {
                if (! isset($this->countedServiceFacts[$key])) {
                    $this->countedServiceFacts[$key] = true;
                    $name = $fact['service'];
                    $this->breakdown[$name] = ($this->breakdown[$name] ?? 0) + $fact['count'];
                    $this->services += $fact['outcome'] ? 0 : $fact['count'];
                }
            }
            return;
        }

        $services = $details['services'] ?? [];
        foreach ($services as $name => $count) {
            $this->breakdown[$name] = ($this->breakdown[$name] ?? 0) + $count;
        }
        $this->services += $details['services_conducted']
            ?? array_sum(array_diff_key($services, array_fill_keys(['Pregnant', 'Non-Pregnant', 'Inconclusive'], true)));
    }

    public static function period(ActivityLog $entry, string $period): string
    {
        $date = $entry->transaction_date
            ? Carbon::parse($entry->transaction_date, ActivityLog::TIMEZONE)
            : $entry->localTime();
        return match ($period) {
            'weekly' => $date->copy()->startOfWeek()->toDateString().' to '.$date->copy()->endOfWeek()->toDateString(),
            'monthly' => $date->format('F Y'),
            'quarterly' => $date->year.' Q'.$date->quarter,
            'annual' => (string) $date->year,
            default => $date->toDateString(),
        };
    }

    public function metrics(): array
    {
        return [
            'Total Activities' => $this->activities,
            'Records Created' => $this->created,
            'Records Updated' => $this->updated,
            'Animals Monitored' => count($this->animals),
            'Services Conducted' => $this->services,
            'Cooperatives Served' => count($this->cooperatives),
        ];
    }

    public function serviceStatements(): array
    {
        $statements = [];
        foreach ($this->breakdown as $service => $count) {
            $statements[] = match ($service) {
                'Calf Drop' => 'Recorded '.number_format($count).' '.Str::plural('calf drop', $count).'.',
                'Pregnancy Diagnosis' => 'Conducted '.number_format($count).' '.Str::plural('pregnancy diagnosis', $count).'.',
                'BCS' => 'Recorded '.number_format($count).' '.Str::plural('body condition score', $count).'.',
                'Pregnant', 'Non-Pregnant', 'Inconclusive' => number_format($count).' '.strtolower($service).' '.Str::plural('result', $count).'.',
                default => 'Recorded '.number_format($count).' '.$service.' '.Str::plural('service', $count).'.',
            };
        }
        return $statements;
    }
}
