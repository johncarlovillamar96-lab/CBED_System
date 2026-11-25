<?php

use App\Support\ActivityFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('module')->nullable()->index();
            $table->text('description')->nullable();
            $table->date('transaction_date')->nullable()->index();
            $table->json('details')->nullable();
            $table->string('operation_key', 64)->nullable()->unique();
            $table->index('occurred_at');
        });
        Schema::create('activity_row_changes', function (Blueprint $table) {
            $table->id();
            $table->string('operation_key', 64)->index();
            $table->string('source_table', 64);
            $table->unsignedBigInteger('record_id');
            $table->json('previous')->nullable();
            $table->json('current')->nullable();
            $table->json('private_changes')->nullable();
        });
        foreach (ActivityFields::TABLES as $table => [, $fieldList]) {
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $action) {
                $this->createTrigger($table, explode(' ', $fieldList), $action);
            }
        }
    }

    private function createTrigger(string $table, array $fields, string $action): void
    {
        $postgres = DB::getDriverName() === 'pgsql';
        $name = $this->triggerName($table, $action);
        $key = $postgres ? "NULLIF(current_setting('cbed.activity_key', true), '')" : '@cbed_activity_key';
        $id = $action === 'DELETE' ? 'OLD.id' : 'NEW.id';
        $previous = $action === 'INSERT' ? 'NULL' : $this->snapshot($table, $fields, 'OLD', $postgres);
        $current = $action === 'DELETE' ? 'NULL' : $this->snapshot($table, $fields, 'NEW', $postgres);
        $privateChanges = 'NULL';
        if ($action === 'UPDATE' && isset(ActivityFields::PRIVATE_FIELDS[$table])) {
            $constructor = $postgres ? 'json_build_object' : 'JSON_OBJECT';
            $flags = array_map(
                fn ($field) => $postgres ? "'$field', OLD.$field IS DISTINCT FROM NEW.$field"
                    : "'$field', NOT (BINARY OLD.$field <=> BINARY NEW.$field)",
                ActivityFields::PRIVATE_FIELDS[$table]
            );
            $privateChanges = $constructor.'('.implode(', ', $flags).')';
        }
        $body = "IF $key IS NOT NULL THEN
            INSERT INTO activity_row_changes (operation_key, source_table, record_id, previous, current, private_changes)
            VALUES ($key, '$table', $id, $previous, $current, $privateChanges);
        END IF;";
        if ($postgres) {
            DB::unprepared("CREATE FUNCTION $name() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN $body RETURN NULL; END; \$\$");
            DB::unprepared("CREATE TRIGGER $name AFTER $action ON $table FOR EACH ROW EXECUTE FUNCTION $name()");
        } else {
            DB::unprepared("CREATE TRIGGER $name AFTER $action ON $table FOR EACH ROW BEGIN $body END");
        }
    }

    private function snapshot(string $table, array $fields, string $row, bool $postgres): string
    {
        $arguments = array_map(fn ($field) => "'$field', $row.$field", $fields);
        if ($table === 'health_records') {
            // Capture only the structured service labels and worksheet indicators.
            $source = "$row.source_data";
            $extract = fn ($path) => $postgres
                ? "jsonb_path_query_first($source::jsonb, '$path')"
                : "JSON_EXTRACT(IF(JSON_VALID($source), $source, '{}'), '$path')";
            foreach (['service_types' => 'type', 'other_service_names' => 'other_service_name'] as $name => $field) {
                $path = '$.services[*].'.$field;
                $value = $postgres ? "jsonb_path_query_array($source::jsonb, '$path')" : $extract($path);
                $arguments[] = "'$name', $value";
            }
            $worksheet = [];
            foreach (explode(' ', 'deworming vitamins_minerals vaccination uterine_flushing artificial_insemination ear_tagging fecalysis recruitment hands_on_milking forage_demo') as $field) {
                $worksheet[] = "'$field', ".$extract('$.health_worksheet.'.$field);
            }
            $constructor = $postgres ? 'json_build_object' : 'JSON_OBJECT';
            $arguments[] = "'worksheet_services', ".$constructor.'('.implode(', ', $worksheet).')';
        }
        $function = $postgres ? 'json_build_object' : 'JSON_OBJECT';
        return $function.'('.implode(', ', $arguments).')';
    }

    private function triggerName(string $table, string $action): string
    {
        return 'activity_'.$table.'_'.strtolower($action);
    }

    public function down(): void
    {
        foreach (array_keys(ActivityFields::TABLES) as $table) {
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $action) {
                $name = $this->triggerName($table, $action);
                if (DB::getDriverName() === 'pgsql') {
                    DB::unprepared("DROP TRIGGER IF EXISTS $name ON $table");
                    DB::unprepared("DROP FUNCTION IF EXISTS $name()");
                } else {
                    DB::unprepared("DROP TRIGGER IF EXISTS $name");
                }
            }
        }
        Schema::dropIfExists('activity_row_changes');
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['module']);
            $table->dropIndex(['transaction_date']);
            $table->dropIndex(['occurred_at']);
            $table->dropUnique(['operation_key']);
            $table->dropColumn(['module', 'description', 'transaction_date', 'details', 'operation_key']);
        });
    }
};
