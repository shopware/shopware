<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApiFileLoader;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReporter;
use Shopware\Core\Framework\Api\Command\StoreApiSchemaMigrationReportCommand;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\DefinitionWithAssociations;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\DefinitionWithJsonOverride;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiSchemaMigrationReportCommand::class)]
#[CoversClass(StoreApiSchemaMigrationReporter::class)]
#[CoversClass(OpenApiFileLoader::class)]
class StoreApiSchemaMigrationReportCommandTest extends TestCase
{
    public function testCommandOutputsMigrationReport(): void
    {
        $commandTester = new CommandTester(new StoreApiSchemaMigrationReportCommand(
            $this->createReporter(),
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
        static::assertContains('JsonOverrideEntity', $report['jsonOverridesPhpGenerated']);
        static::assertContains('TestEntityWithAssociations', $report['phpGeneratedOnlyWithoutAllowlist']);
    }

    public function testCommandCanFailOnMigrationMismatches(): void
    {
        $commandTester = new CommandTester(new StoreApiSchemaMigrationReportCommand(
            $this->createReporter(),
            $this->createDefinitionRegistry(),
        ));

        $exitCode = $commandTester->execute(['--fail-on-mismatch' => true], ['capture_stderr_separately' => true]);

        static::assertSame(Command::FAILURE, $exitCode);
    }

    public function testCommandFailsForInvalidScope(): void
    {
        $commandTester = new CommandTester(new StoreApiSchemaMigrationReportCommand(
            $this->createReporter(),
            $this->createDefinitionRegistry(),
        ));

        $exitCode = $commandTester->execute(['--scope' => 'extensions'], ['capture_stderr_separately' => true]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('The scope option must be one of: core, all.', $commandTester->getDisplay());
    }

    private function createReporter(): StoreApiSchemaMigrationReporter
    {
        return new StoreApiSchemaMigrationReporter(
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/../ApiDefinition/Generator/_fixtures'],
            ],
            new BundleSchemaPathCollection([]),
        );
    }

    private function createDefinitionRegistry(): SalesChannelDefinitionInstanceRegistry
    {
        $definitionRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $definitionRegistry->method('getDefinitions')->willReturn($this->createDefinitions());

        return $definitionRegistry;
    }

    /**
     * @return array<string, EntityDefinition>
     */
    private function createDefinitions(): array
    {
        return (new StaticDefinitionInstanceRegistry(
            [
                DefinitionWithAssociations::class,
                DefinitionWithJsonOverride::class,
                SimpleDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        ))->getDefinitions();
    }
}
