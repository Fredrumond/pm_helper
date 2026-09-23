<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\AuditingServiceProvider;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Models\Audit;
use OwenIt\Auditing\Resolvers\UserResolver;
use Tests\TestCase;

class AuditsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_audits_table_exists_with_package_columns(): void
    {
        $this->assertTrue(Schema::hasTable('audits'));

        foreach ([
            'user_type',
            'user_id',
            'event',
            'auditable_type',
            'auditable_id',
            'old_values',
            'new_values',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('audits', $column), "Missing column: {$column}");
        }
    }

    public function test_audit_model_can_query_the_audits_table(): void
    {
        $this->assertSame(0, Audit::query()->count());
    }

    public function test_auditable_trait_and_contract_are_available(): void
    {
        $this->assertTrue(trait_exists(Auditable::class));
        $this->assertTrue(interface_exists(AuditableContract::class));
    }

    public function test_published_audit_config_uses_database_driver_and_authenticated_user_resolver(): void
    {
        $this->assertTrue(app()->providerIsLoaded(AuditingServiceProvider::class));
        $this->assertSame('database', config('audit.driver'));
        $this->assertSame(UserResolver::class, config('audit.user.resolver'));
    }
}
