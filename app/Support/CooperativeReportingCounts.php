<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

final class CooperativeReportingCounts
{
    public const CALF_FARMER_HERDS = [
        'AST' => [
            'Adeline B. Flores' => 'ADL',
            'Flores, Ernie Lita June' => 'ADL',
            'Lagman, William' => 'SVD',
            'Caingat, Timothy John' => 'SVD',
        ],
        'JFC' => [
            'Santos, Bernie' => 'BMF',
            'David, Jerry' => 'XCD',
        ],
    ];

    public const CALF_FARMER_HERD_DEFAULTS = ['JFC' => 'JFC'];

    public static function workbookHerds(): array
    {
        return json_decode(file_get_contents(config_path('cooperative-reporting-herds.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function calfHerdCode(?string $cooperativeCode, ?string $selectedCode, ?int $farmerId = null, ?string $damRegistry = null): ?string
    {
        if ($cooperativeCode === 'ADL' && $damRegistry !== null
            && in_array($damRegistry, array_merge(...array_values(self::workbookHerds()['AST'])), true)) {
            $cooperativeCode = 'AST';
        }

        $rules = self::CALF_FARMER_HERDS[$cooperativeCode] ?? [];
        $assignedCode = self::CALF_FARMER_HERD_DEFAULTS[$cooperativeCode] ?? null;
        if ($rules && $farmerId !== null) {
            $farmer = DB::table('farmers')->where('id', $farmerId)->first(['first_name', 'middle_name', 'last_name']);
            if ($farmer) {
                $name = self::farmerNameKey(implode(' ', (array) $farmer));
                foreach ($rules as $ruleName => $ruleCode) {
                    if (self::farmerNameKey($ruleName) === $name) {
                        $assignedCode = $ruleCode;
                        break;
                    }
                }
            }
        }
        if ($assignedCode !== null) {
            abort_if($selectedCode !== null && $selectedCode !== $assignedCode, 422,
                'Use the herd code assigned to this farmer.');

            return $assignedCode;
        }
        if ($selectedCode === null) {
            abort_if($cooperativeCode === 'AST', 422, 'Select ADL or SVD as the herd code for this calf.');
            return $cooperativeCode;
        }

        $allowed = array_keys(self::workbookHerds()[$cooperativeCode] ?? []);
        abort_unless(in_array($selectedCode, $allowed, true), 422, 'Choose a herd code assigned to this cooperative.');

        return $selectedCode;
    }

    private static function farmerNameKey(string $name): string
    {
        $parts = preg_split('/[^a-z0-9]+/', strtolower($name), -1, PREG_SPLIT_NO_EMPTY);
        sort($parts);

        return implode(' ', $parts);
    }

    public static function whereAnimalMembership(Builder $animals, int $cooperativeId): Builder
    {
        $cooperativeCode = DB::table('cooperatives')->where('id', $cooperativeId)->value('cooperative_code');
        return $animals->where(function (Builder $query) use ($cooperativeId, $cooperativeCode) {
            $query->whereRaw('COALESCE(animal.cooperative_id, farmer.cooperative_id) = ?', [$cooperativeId]);
            if ($cooperativeCode === 'AST') {
                $sourceId = DB::table('cooperatives')->where('cooperative_code', 'ADL')->value('id');
                $registries = array_merge(...array_values(self::workbookHerds()['AST']));
                if ($sourceId !== null) {
                    $query->orWhere(fn (Builder $source) => $source
                        ->whereRaw('COALESCE(animal.cooperative_id, farmer.cooperative_id) = ?', [$sourceId])
                        ->where(fn (Builder $identity) => $identity->whereIn('animal.registry_number', $registries)
                            ->orWhereIn('animal.animal_id', $registries)));
                }
            }
        });
    }

    public static function records(): Collection
    {
        $cooperatives = DB::table('cooperatives')->orderBy('cooperative_name')->get();
        $idsByCode = $cooperatives->pluck('id', 'cooperative_code');
        $workbookHerds = self::workbookHerds();
        $assignments = [];
        $herdTotals = [];
        foreach ($workbookHerds as $code => $herds) {
            foreach ($herds as $herd => $registries) {
                $herdTotals[$code][$herd] = ['animals' => 0, 'recipients' => []];
                foreach ($registries as $registry) {
                    $assignments[$registry] = ['cooperative' => $code, 'herd' => $herd];
                }
            }
        }

        $totals = [];
        $trustees = DB::table('farmers')->whereNotNull('cooperative_id')->get(['id', 'cooperative_id'])
            ->groupBy('cooperative_id')->map(fn ($farmers) => $farmers->pluck('id')->all());
        $animals = DB::table('carabaos as animal')
            ->leftJoin('farmers as farmer', 'farmer.id', '=', 'animal.farmer_id')
            ->select('animal.registry_number', 'animal.animal_id', 'farmer.id as farmer_id')
            ->selectRaw('COALESCE(animal.cooperative_id, farmer.cooperative_id) AS cooperative_id')
            ->cursor();
        foreach ($animals as $animal) {
            $id = $animal->cooperative_id;
            $assignment = $assignments[$animal->registry_number] ?? $assignments[$animal->animal_id] ?? null;
            $code = $assignment['cooperative'] ?? null;
            if ($code && $idsByCode->has($code) && ($id == $idsByCode[$code]
                    || ($code === 'AST' && $idsByCode->has('ADL') && $id == $idsByCode['ADL']))) {
                $id = $idsByCode[$code];
                $herd = $assignment['herd'];
                $herdTotals[$code][$herd]['animals']++;
                if ($animal->farmer_id !== null) {
                    $herdTotals[$code][$herd]['recipients'][] = $animal->farmer_id;
                    $trustees[$id] = [...$trustees->get($id, []), $animal->farmer_id];
                }
            }
            if ($id === null) {
                continue;
            }
            $totals[$id]['animals'] = ($totals[$id]['animals'] ?? 0) + 1;
            if ($animal->farmer_id !== null) {
                $totals[$id]['recipients'][] = $animal->farmer_id;
            }
        }

        return $cooperatives->map(function ($cooperative) use ($totals, $trustees, $herdTotals) {
            $total = $totals[$cooperative->id] ?? [];
            $cooperative->trustee_count = count(array_unique($trustees->get($cooperative->id, [])));
            $cooperative->recipient_count = count(array_unique($total['recipients'] ?? []));
            $cooperative->animals_released = $total['animals'] ?? 0;
            $cooperative->herds = collect($herdTotals[$cooperative->cooperative_code] ?? [])
                ->map(function ($herd, $code) {
                    $recipients = count(array_unique($herd['recipients']));

                    return ['code' => $code, 'trustee_count' => $recipients, 'recipient_count' => $recipients, 'animals_released' => $herd['animals']];
                })->values();

            return $cooperative;
        });
    }
}
