<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReport;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiSchemaMigrationReport::class)]
class StoreApiSchemaMigrationReportTest extends TestCase
{
    public function testDetectsMismatches(): void
    {
        $report = $this->createReport(
            jsonOverridesPhpGenerated: ['JsonOverrideEntity'],
        );

        static::assertTrue($report->hasMismatches());
    }

    public function testDetectsReportWithoutMismatches(): void
    {
        static::assertFalse($this->createReport()->hasMismatches());
    }

    public function testSerializesReportBuckets(): void
    {
        $report = $this->createReport(
            jsonOverridesPhpGenerated: ['JsonOverrideEntity'],
            phpGeneratedOnly: ['Product'],
        );

        static::assertSame([
            'jsonOverridesPhpGenerated' => ['JsonOverrideEntity'],
            'phpGeneratedOnly' => ['Product'],
            'phpGeneratedOnlyAllowed' => [],
            'phpGeneratedOnlyWithoutAllowlist' => [],
            'jsonWithoutPhpGenerated' => [],
            'allowlistWithoutPhpGeneratedOnlySchema' => [],
            'allowlistWithoutPhpGeneratedSchema' => [],
        ], $report->jsonSerialize());
    }

    /**
     * @param list<string> $jsonOverridesPhpGenerated
     * @param list<string> $phpGeneratedOnly
     * @param list<string> $phpGeneratedOnlyAllowed
     * @param list<string> $phpGeneratedOnlyWithoutAllowlist
     * @param list<string> $jsonWithoutPhpGenerated
     * @param list<string> $allowlistWithoutPhpGeneratedOnlySchema
     * @param list<string> $allowlistWithoutPhpGeneratedSchema
     */
    private function createReport(
        array $jsonOverridesPhpGenerated = [],
        array $phpGeneratedOnly = [],
        array $phpGeneratedOnlyAllowed = [],
        array $phpGeneratedOnlyWithoutAllowlist = [],
        array $jsonWithoutPhpGenerated = [],
        array $allowlistWithoutPhpGeneratedOnlySchema = [],
        array $allowlistWithoutPhpGeneratedSchema = [],
    ): StoreApiSchemaMigrationReport {
        return new StoreApiSchemaMigrationReport(
            jsonOverridesPhpGenerated: $jsonOverridesPhpGenerated,
            phpGeneratedOnly: $phpGeneratedOnly,
            phpGeneratedOnlyAllowed: $phpGeneratedOnlyAllowed,
            phpGeneratedOnlyWithoutAllowlist: $phpGeneratedOnlyWithoutAllowlist,
            jsonWithoutPhpGenerated: $jsonWithoutPhpGenerated,
            allowlistWithoutPhpGeneratedOnlySchema: $allowlistWithoutPhpGeneratedOnlySchema,
            allowlistWithoutPhpGeneratedSchema: $allowlistWithoutPhpGeneratedSchema,
        );
    }
}
