<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return; // RLS and triggers are PostgreSQL-only; skip for SQLite in tests
        }

        // ─────────────────────────────────────────────────────────────
        // A. Enable Row-Level Security on all 13 PHI tables
        // ─────────────────────────────────────────────────────────────
        $tables = [
            'clients',
            'service_logs',
            'authorizations',
            'service_groups',
            'wble_employers',
            'wble_placements',
            'wble_payroll_records',
            'curricula',
            'generated_forms',
            'document_upload_links',
            'authorization_alerts',
            'crp_audit_logs',
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        }

        // ─────────────────────────────────────────────────────────────
        // B. RLS Policies — tenant isolation via app.current_crp_id
        // ─────────────────────────────────────────────────────────────
        $standardTables = [
            'clients',
            'service_logs',
            'authorizations',
            'service_groups',
            'wble_employers',
            'wble_placements',
            'wble_payroll_records',
            'curricula',
            'generated_forms',
            'document_upload_links',
            'authorization_alerts',
        ];

        foreach ($standardTables as $table) {
            DB::statement("
                CREATE POLICY {$table}_tenant_isolation ON {$table}
                    USING (crp_id = current_setting('app.current_crp_id', true)::integer)
            ");
        }

        // audit logs: also allow when crp_id is null (genesis records, system events)
        DB::statement("
            CREATE POLICY crp_audit_logs_tenant_isolation ON crp_audit_logs
                USING (
                    crp_id = current_setting('app.current_crp_id', true)::integer
                    OR crp_id IS NULL
                )
        ");

        // ─────────────────────────────────────────────────────────────
        // C. Immutable audit log trigger — blocks UPDATE/DELETE
        // ─────────────────────────────────────────────────────────────
        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_audit_modification()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'Audit logs are immutable. UPDATE and DELETE operations are prohibited.';
            END;
            \$\$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER audit_logs_immutable
                BEFORE UPDATE OR DELETE ON crp_audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_modification()
        ");

        // ─────────────────────────────────────────────────────────────
        // D. Locked service log trigger — blocks modification after lock
        // ─────────────────────────────────────────────────────────────
        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_locked_service_log_modification()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF OLD.locked_at IS NOT NULL THEN
                    RAISE EXCEPTION 'Service log is locked. Modification is prohibited.';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER service_logs_locked_immutable
                BEFORE UPDATE ON service_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_locked_service_log_modification()
        ");

        // ─────────────────────────────────────────────────────────────
        // E. Cascade lock trigger — locks generated_forms when service_log locks
        // ─────────────────────────────────────────────────────────────
        DB::statement("
            CREATE OR REPLACE FUNCTION cascade_service_log_lock()
            RETURNS TRIGGER AS \$\$
            BEGIN
                UPDATE generated_forms
                SET locked_at = NEW.locked_at, locked_by = NEW.locked_by
                WHERE service_log_id = NEW.id AND generated_forms.locked_at IS NULL;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql
        ");

        DB::statement("
            CREATE TRIGGER service_log_lock_cascade
                AFTER UPDATE OF locked_at ON service_logs
                FOR EACH ROW
                WHEN (NEW.locked_at IS NOT NULL AND (OLD.locked_at IS NULL OR OLD.locked_at != NEW.locked_at))
                EXECUTE FUNCTION cascade_service_log_lock()
        ");
    }

    public function down(): void
    {
        // Drop triggers
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_immutable ON crp_audit_logs');
        DB::statement('DROP TRIGGER IF EXISTS service_logs_locked_immutable ON service_logs');
        DB::statement('DROP TRIGGER IF EXISTS service_log_lock_cascade ON service_logs');
        DB::statement('DROP FUNCTION IF EXISTS prevent_audit_modification()');
        DB::statement('DROP FUNCTION IF EXISTS prevent_locked_service_log_modification()');
        DB::statement('DROP FUNCTION IF EXISTS cascade_service_log_lock()');

        // Drop RLS policies
        $tables = [
            'clients', 'service_logs', 'authorizations', 'service_groups',
            'wble_employers', 'wble_placements', 'wble_payroll_records',
            'curricula', 'generated_forms', 'document_upload_links',
            'authorization_alerts', 'crp_audit_logs',
        ];

        foreach ($tables as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
