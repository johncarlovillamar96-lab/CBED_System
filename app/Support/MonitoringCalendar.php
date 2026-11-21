<?php

namespace App\Support;

use Illuminate\Support\Collection;

class MonitoringCalendar
{
    public static function serviceCounts(Collection $events): Collection
    {
        return $events
            ->groupBy(fn ($event) => trim((string) $event->cooperative_name) ?: 'Unassigned cooperative')
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Collection $activities) => $activities->flatMap(self::services(...))
                ->countBy()->sortKeys(SORT_NATURAL | SORT_FLAG_CASE));
    }

    private static function services(object $event): Collection
    {
        $details = (array) ($event->details ?? []);
        $services = $event->calendar_services ?? [];
        if ($services === [] && is_array($details['services'] ?? null)) {
            $services = collect($details['services'])->filter(fn ($service) => is_array($service)
                && filter_var($service['selected'] ?? true, FILTER_VALIDATE_BOOLEAN))
                ->map(fn ($service) => ($service['type'] ?? '') === 'Other Services'
                    ? ($service['other_service_name'] ?? 'Other Services') : ($service['type'] ?? ''))
                ->all();
        }

        $names = collect($services)->filter(fn ($name) => is_string($name) && trim($name) !== '');
        if ($names->isEmpty()) {
            $name = str_starts_with($event->service, 'Mating / Breeding')
                ? ($details['mating_type'] ?? $details['breeding_method'] ?? $details['method'] ?? $event->service)
                : $event->service;
            $names->push($name);
        }

        return $names->map(self::serviceLabel(...))->unique(fn ($name) => mb_strtolower($name))->values();
    }

    private static function serviceLabel(string $name): string
    {
        $name = trim($name);
        $key = preg_replace('/[^a-z0-9]+/', '', strtolower($name));

        return match ($key) {
            'ai', 'artificialinsemination', 'artificialinseminationai', 'matingbreedingai' => 'AI',
            'n', 'nm', 'natural', 'naturalmating', 'naturalmatingnm', 'matingbreedingnm' => 'Natural Mating',
            'deworm', 'deworming', 'dewormingservices' => 'Deworming',
            'eartag', 'eartagging', 'eartagged', 'eartaggingactivities' => 'Ear Tagging',
            'vac', 'vaccination', 'vacvaccination', 'vaccinations' => 'Vaccination',
            'vitamins', 'vitaminsupplementation', 'vitaminsminerals' => 'Vitamins',
            'fs', 'fecalysis', 'fsfecalysis' => 'Fecalysis',
            'flushing', 'uterineflushing' => 'Uterine Flushing',
            default => $name,
        };
    }
}
