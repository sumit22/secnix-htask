<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ContractsExportTest extends TestCase
{
    public function test_exports_enums_as_typescript_const_objects_and_unions(): void
    {
        $output = $this->export();

        self::assertStringStartsWith('// AUTO-GENERATED', $output);
        self::assertStringContainsString("export const LookupStatus = {\n  Queued: \"queued\",\n  Leased: \"leased\",", $output);
        self::assertStringContainsString('export type LookupStatus = (typeof LookupStatus)[keyof typeof LookupStatus];', $output);

        foreach (['IndicatorType', 'Verdict', 'UserRole', 'ApiErrorCode'] as $enum) {
            self::assertStringContainsString("export const {$enum} = {", $output);
        }
    }

    public function test_exports_the_lookup_state_machine(): void
    {
        $output = $this->export();

        self::assertStringContainsString("  queued: [\"leased\"],\n  leased: [\"completed\", \"expired\"],\n  completed: [],\n  expired: [],", $output);
        self::assertStringContainsString('export const ACTIVE_LOOKUP_STATUSES = ["queued", "leased"] as const', $output);
    }

    public function test_output_is_deterministic(): void
    {
        self::assertSame($this->export(), $this->export());
    }

    private function export(): string
    {
        self::assertSame(0, Artisan::call('contracts:export'));

        return Artisan::output();
    }
}
