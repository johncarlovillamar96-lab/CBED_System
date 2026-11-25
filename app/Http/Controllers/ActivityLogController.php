<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityAccomplishments;
use App\Support\TablePagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ActivityLogController extends Controller
{
    public function index(Request $request, MonitoringReportController $monitoring)
    {
        if (! $request->user()->isAdmin()) {
            abort_if($request->filled('format'), 403);
            return $monitoring->activityLogs($request);
        }

        $filters = array_filter($request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'year' => ['nullable', 'integer', 'between:1900,9998'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'user' => ['nullable', 'integer', 'min:1'],
            'cooperative' => ['nullable', 'integer', 'min:1'],
            'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:32'],
            'role' => ['nullable', 'in:'.implode(',', array_keys(User::ROLES))],
            'animal' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'in:daily,weekly,monthly,quarterly,annual'],
            'format' => ['nullable', 'in:excel,print'],
        ]));
        $format = $filters['format'] ?? null;
        unset($filters['format']);
        $period = $filters['period'] ?? 'daily';
        $query = $this->filtered($filters)->latest('occurred_at')->latest('id');
        $summary = new ActivityAccomplishments;
        $periods = [];
        foreach ((clone $query)->cursor() as $entry) {
            $summary->add($entry);
            $key = ActivityAccomplishments::period($entry, $period);
            $periods[$key] ??= new ActivityAccomplishments;
            $periods[$key]->add($entry);
        }

        $metrics = $summary->metrics();
        if ($format === 'excel') {
            return $this->excel($query, $metrics, $periods);
        }
        if ($format === 'print') {
            $entries = $query->cursor();
            return view('activity-print', compact('entries', 'metrics', 'periods', 'period'));
        }

        $entries = $query->paginate(TablePagination::size($request))->withQueryString();
        $options = [
            'user' => ActivityLog::query()->whereIn('id', ActivityLog::recordedTransactions()->selectRaw('MAX(id)')->groupBy('actor_id'))
                ->orderBy('actor_name')->pluck('actor_name', 'actor_id'),
            'cooperative' => DB::table('cooperatives')->orderBy('cooperative_name')->pluck('cooperative_name', 'id'),
            'module' => ActivityLog::recordedTransactions()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module', 'module'),
            'action' => ActivityLog::recordedTransactions()->distinct()->orderBy('action')->pluck('action', 'action'),
            'role' => User::ROLES,
            'period' => ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'],
        ];

        return view('system-activity', compact('entries', 'options', 'filters', 'metrics', 'periods', 'period'));
    }

    private function filtered(array $filters): Builder
    {
        $query = ActivityLog::recordedTransactions();
        foreach (['user' => 'actor_id', 'module' => 'module', 'action' => 'action', 'role' => 'actor_role'] as $key => $column) {
            if (isset($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }
        foreach (['from' => '>=', 'to' => '<'] as $key => $operator) {
            if (isset($filters[$key])) {
                $date = Carbon::parse($filters[$key], ActivityLog::TIMEZONE)->startOfDay();
                $query->where('occurred_at', $operator, ($key === 'to' ? $date->addDay() : $date)->utc());
            }
        }
        if (isset($filters['year']) || isset($filters['month'])) {
            $mysql = DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb';
            $local = $mysql ? 'DATE_ADD(occurred_at, INTERVAL 8 HOUR)' : "occurred_at + INTERVAL '8 hours'";
            foreach (['year', 'month'] as $part) {
                if (isset($filters[$part])) {
                    $query->whereRaw("EXTRACT($part FROM $local) = ?", [(int) $filters[$part]]);
                }
            }
        }
        if (isset($filters['cooperative'])) {
            $query->whereJsonContains('details->cooperative_ids', (int) $filters['cooperative']);
        }
        if (isset($filters['animal'])) {
            $query->whereJsonContains('details->animal_ids_normalized', mb_strtolower(trim($filters['animal'])));
        }
        if (isset($filters['search'])) {
            $term = '%'.mb_strtolower(trim($filters['search'])).'%';
            $query->where(fn (Builder $query) => $query->whereRaw('LOWER(description) LIKE ?', [$term])
                ->orWhereRaw('LOWER(actor_name) LIKE ?', [$term]));
        }
        return $query;
    }

    private function excel(Builder $query, array $metrics, array $periods)
    {
        return response()->streamDownload(function () use ($query, $metrics, $periods) {
            $book = new Spreadsheet;
            $summary = $book->getActiveSheet()->setTitle('Accomplishments');
            $summary->fromArray([array_keys($metrics), array_values($metrics)], null, 'A1', true);
            $summary->fromArray(['Period', 'Accomplishment', 'Count'], null, 'A4', true);
            $line = 5;
            foreach ($periods as $period => $totals) {
                foreach ($totals->metrics() + $totals->breakdown as $label => $total) {
                    $this->writeRow($summary, $line++, [$period, $label, $total]);
                }
            }
            $sheet = $book->createSheet()->setTitle('Activities');
            $sheet->fromArray(['No.', 'Date and Time (Asia/Manila)', 'User', 'First Name', 'Last Name', 'Module', 'Action', 'Cooperative', 'Activity Description', 'Transaction Dates', 'Animal IDs', 'Audit ID'], null, 'A1');
            $line = 2;
            foreach ($query->cursor() as $entry) {
                $details = $entry->details ?? [];
                $this->writeRow($sheet, $line, [$line - 1, $entry->localTime()->format('Y-m-d H:i:s'), $entry->actor_name.' (#'.$entry->actor_id.')',
                    $entry->actor_first_name ?? '', $entry->actor_last_name ?? '',
                    $entry->module ?? 'System', $entry->action, implode(', ', $details['cooperatives'] ?? []),
                    $entry->activityDescription(), implode(', ', $details['transaction_dates'] ?? []),
                    implode(', ', $details['animal_ids'] ?? []), $entry->id]);
                $line++;
            }
            foreach ([$summary, $sheet] as $tab) {
                $tab->freezePane('A2');
                $tab->getStyle('1:1')->getFont()->setBold(true);
            }
            $sheet->getColumnDimension('I')->setWidth(75);
            $sheet->getStyle('I2:I'.max(2, $line - 1))->getAlignment()->setWrapText(true);
            foreach (['B' => 27, 'C' => 28, 'D' => 20, 'E' => 20, 'F' => 24, 'G' => 16, 'H' => 38, 'J' => 21, 'K' => 36] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            $summary->getColumnDimension('A')->setWidth(26);
            $summary->getColumnDimension('B')->setWidth(29);
            for ($column = 'C'; $column <= 'F'; $column++) {
                $summary->getColumnDimension($column)->setWidth(23);
            }
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'activity-and-accomplishments.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function writeRow($sheet, int $line, array $values): void
    {
        foreach ($values as $offset => $value) {
            $sheet->setCellValueExplicit([$offset + 1, $line], $value, is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        }
    }
}
