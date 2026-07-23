<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApiFileLoader;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReporter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_extensionFixtures\ExtensionDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\CustomBundleWithApiSchema\ShopwareBundleWithName;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\DefinitionWithAssociations;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\DefinitionWithJsonOverride;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiSchemaMigrationReporter::class)]
#[CoversClass(OpenApiFileLoader::class)]
class StoreApiSchemaMigrationReporterTest extends TestCase
{
    public function testReportGroupsStoreApiSchemaMigrationState(): void
    {
        $report = $this->createReporter()->report($this->createDefinitions());

        static::assertContains('JsonOverrideEntity', $report['jsonOverridesPhpGenerated']);
        static::assertContains('JsonOverrideEntity', $report['jsonOverridesPhpGeneratedAllowed']);
        static::assertNotContains('JsonOverrideEntity', $report['jsonOverridesPhpGeneratedWithoutAllowlist']);
        static::assertContains('Simple', $report['phpGeneratedOnly']);
        static::assertContains('TestEntityWithAssociations', $report['phpGeneratedOnly']);
        static::assertContains('Simple', $report['phpGeneratedOnlyAllowed']);
        static::assertNotContains('Simple', $report['phpGeneratedOnlyWithoutAllowlist']);
        static::assertContains('TestEntityWithAssociations', $report['phpGeneratedOnlyWithoutAllowlist']);
        static::assertContains('infoConfigResponse', $report['jsonWithoutPhpGenerated']);
        static::assertContains('StaleJsonOverrideAllowlistEntry', $report['allowlistWithoutJsonOverridesPhpGeneratedSchema']);
        static::assertContains('StalePhpGeneratedOnlyAllowlistEntry', $report['allowlistWithoutPhpGeneratedOnlySchema']);
        static::assertContains('StalePhpGeneratedOnlyAllowlistEntry', $report['allowlistWithoutPhpGeneratedSchema']);
    }

    public function testCoreScopeIgnoresExtensionDefinitionsAndSchemaFiles(): void
    {
        $reporter = $this->createReporter(new BundleSchemaPathCollection([new ShopwareBundleWithName()]));
        $definitions = $this->createDefinitions([ExtensionDefinition::class]);

        $coreReport = $reporter->report($definitions, StoreApiSchemaMigrationReporter::SCOPE_CORE);
        static::assertContains('Simple', $coreReport['phpGeneratedOnly']);
        static::assertNotContains('Simple', $coreReport['jsonOverridesPhpGenerated']);
        static::assertNotContains('Extension', $coreReport['phpGeneratedOnly']);
        static::assertNotContains('Presentation', $coreReport['jsonWithoutPhpGenerated']);

        $allReport = $reporter->report($definitions, StoreApiSchemaMigrationReporter::SCOPE_ALL);
        static::assertContains('Simple', $allReport['jsonOverridesPhpGenerated']);
        static::assertContains('Extension', $allReport['phpGeneratedOnly']);
        static::assertContains('Presentation', $allReport['jsonWithoutPhpGenerated']);
    }

    private function createReporter(?BundleSchemaPathCollection $bundleSchemaPathCollection = null): StoreApiSchemaMigrationReporter
    {
        return new StoreApiSchemaMigrationReporter(
            new OpenApiDefinitionSchemaBuilder(),
            [
                'Framework' => ['path' => __DIR__ . '/_fixtures'],
            ],
            $bundleSchemaPathCollection ?? new BundleSchemaPathCollection([]),
        );
    }

    /**
     * @param list<class-string<EntityDefinition>> $additionalDefinitionClasses
     *
     * @return array<string, EntityDefinition>
     */
    private function createDefinitions(array $additionalDefinitionClasses = []): array
    {
        return (new StaticDefinitionInstanceRegistry(
            array_merge([
                DefinitionWithAssociations::class,
                DefinitionWithJsonOverride::class,
                SimpleDefinition::class,
            ], $additionalDefinitionClasses),
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        ))->getDefinitions();
    }
}
