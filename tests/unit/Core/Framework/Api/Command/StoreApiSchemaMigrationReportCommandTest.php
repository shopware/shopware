<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReport;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReporter;
use Shopware\Core\Framework\Api\Command\StoreApiSchemaMigrationReportCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiSchemaMigrationReportCommand::class)]
class StoreApiSchemaMigrationReportCommandTest extends TestCase
{
    public function testCommandOutputsMigrationReport(): void
    {
        $commandTester = new CommandTester(new StoreApiSchemaMigrationReportCommand(
            $this->createReporter($this->createReport()),
            $this->createDefinitionRegistry(),
        ));

        $commandTester->execute([], ['capture_stderr_separately' => true]);

        $commandTester->assertCommandIsSuccessful();

        $report = json_decode($commandTester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($report);
        static::assertArrayHasKey('jsonOverridesPhpGenerated', $report);
        static::assertIsArray($report['jsonOverridesPhpGenerated']);
        static::assertArrayHasKey('jsonOverridesPhpGeneratedWithoutAllowlist', $report);
        static::assertIsArray($report['jsonOverridesPhpGeneratedWithoutAllowlist']);
        static::assertArrayHasKey('phpGeneratedOnlyWithoutAllowlist', $report);
        static::assertIsArray($report['phpGeneratedOnlyWithoutAllowlist']);
        static::assertSame(['JsonOverrideEntity'], $report['jsonOverridesPhpGenerated']);
        static::assertSame(['TestEntityWithAssociations'], $report['phpGeneratedOnlyWithoutAllowlist']);
    }

    public function testCommandCanFailOnMigrationMismatches(): void
    {
        $commandTester = new CommandTester(new StoreApiSchemaMigrationReportCommand(
            $this->createReporter($this->createReport()),
            $this->createDefinitionRegistry(),
        ));

        $exitCode = $commandTester->execute(['--fail-on-mismatch' => true], ['capture_stderr_separately' => true]);

        static::assertSame(Command::FAILURE, $exitCode);
    }

    public function testCommandFailsForInvalidScope(): void
    {
        $commandTester = new CommandTester(new StoreApiSchemaMigrationReportCommand(
            $this->createReporter($this->createReport()),
            $this->createDefinitionRegistry(),
        ));

        $exitCode = $commandTester->execute(['--scope' => 'extensions'], ['capture_stderr_separately' => true]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('The scope option must be one of: core, all.', $commandTester->getDisplay());
    }

    private function createReporter(StoreApiSchemaMigrationReport $report): StoreApiSchemaMigrationReporter
    {
        $reporter = static::createStub(StoreApiSchemaMigrationReporter::class);
        $reporter->method('report')->willReturn($report);

        return $reporter;
    }

    private function createDefinitionRegistry(): SalesChannelDefinitionInstanceRegistry
    {
        $definitionRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $definitionRegistry->method('getDefinitions')->willReturn([]);

        return $definitionRegistry;
    }

    private function createReport(): StoreApiSchemaMigrationReport
    {
        return new StoreApiSchemaMigrationReport(
            jsonOverridesPhpGenerated: ['JsonOverrideEntity'],
            jsonOverridesPhpGeneratedAllowed: [],
            jsonOverridesPhpGeneratedWithoutAllowlist: [],
            phpGeneratedOnly: [],
            phpGeneratedOnlyAllowed: [],
            phpGeneratedOnlyWithoutAllowlist: ['TestEntityWithAssociations'],
            jsonWithoutPhpGenerated: [],
            allowlistWithoutJsonOverridesPhpGeneratedSchema: [],
            allowlistWithoutPhpGeneratedOnlySchema: [],
            allowlistWithoutPhpGeneratedSchema: [],
        );
    }
}
