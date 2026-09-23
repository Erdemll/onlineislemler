<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contract_acceptance_events', function (Blueprint $table) {
            $table->string('hash_version', 30)
                ->default('sha256-v1')
                ->after('event_hash');
        });

        $this->createImmutabilityTriggers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropImmutabilityTriggers();

        Schema::table('contract_acceptance_events', function (Blueprint $table) {
            $table->dropColumn('hash_version');
        });
    }

    private function createImmutabilityTriggers(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            foreach ($this->protectedTables() as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_prevent_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable contract audit record'");
                DB::unprepared("CREATE TRIGGER {$table}_prevent_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable contract audit record'");
            }

            return;
        }

        if ($driver === 'sqlite') {
            foreach ($this->protectedTables() as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_prevent_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'Immutable contract audit record'); END");
                DB::unprepared("CREATE TRIGGER {$table}_prevent_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'Immutable contract audit record'); END");
            }
        }
    }

    private function dropImmutabilityTriggers(): void
    {
        foreach ($this->protectedTables() as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_prevent_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_prevent_delete");
        }
    }

    /** @return list<string> */
    private function protectedTables(): array
    {
        return ['contract_acceptances', 'contract_acceptance_events'];
    }
};
