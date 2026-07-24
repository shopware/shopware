<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AppAdministrationSnippetDefinition;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReporter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Definition\GroupByTestDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_extensionFixtures\ExtensionDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\CustomBundleWithApiSchema\ShopwareBundleWithName;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiSchemaMigrationReporter::class)]
class StoreApiSchemaMigrationReporterTest extends TestCase
{
    public function testReportGroupsStoreApiSchemaMigrationState(): void
    {
        $report = $this->createReporter()->report($this->createDefinitions());

        static::assertContains('GroupByTest', $report->phpGeneratedOnly);
        static::assertContains('AppAdministrationSnippet', $report->phpGeneratedOnly);
        static::assertContains('AppAdministrationSnippet', $report->phpGeneratedOnlyAllowed);
        static::assertContains('GroupByTest', $report->phpGeneratedOnlyWithoutAllowlist);
        static::assertContains('CalculatedPrice', $report->jsonWithoutPhpGenerated);
        static::assertSame([], $report->jsonOverridesPhpGenerated);
        static::assertNotContains('Category', $report->jsonOverridesPhpGeneratedWithoutAllowlist);
        static::assertContains('Category', $report->allowlistWithoutJsonOverridesPhpGeneratedSchema);
        static::assertContains('Currency', $report->allowlistWithoutPhpGeneratedOnlySchema);
        static::assertContains('Currency', $report->allowlistWithoutPhpGeneratedSchema);
    }

    public function testCoreScopeIgnoresExtensionDefinitionsAndSchemaFiles(): void
    {
        $reporter = $this->createReporter(new BundleSchemaPathCollection([new ShopwareBundleWithName()]));
        $definitions = $this->createDefinitions([ExtensionDefinition::class]);

        $coreReport = $reporter->report($definitions, StoreApiSchemaMigrationReporter::SCOPE_CORE);
        static::assertContains('GroupByTest', $coreReport->phpGeneratedOnly);
        static::assertContains('AppAdministrationSnippet', $coreReport->phpGeneratedOnly);
        static::assertNotContains('Extension', $coreReport->phpGeneratedOnly);
        static::assertNotContains('Presentation', $coreReport->jsonWithoutPhpGenerated);

        $allReport = $reporter->report($definitions, StoreApiSchemaMigrationReporter::SCOPE_ALL);
        static::assertContains('GroupByTest', $allReport->phpGeneratedOnly);
        static::assertContains('Extension', $allReport->phpGeneratedOnly);
        static::assertContains('Presentation', $allReport->jsonWithoutPhpGenerated);
    }

    private function createReporter(?BundleSchemaPathCollection $bundleSchemaPathCollection = null): StoreApiSchemaMigrationReporter
    {
        return new StoreApiSchemaMigrationReporter(
            new OpenApiDefinitionSchemaBuilder(),
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
                AppAdministrationSnippetDefinition::class,
                GroupByTestDefinition::class,
            ], $additionalDefinitionClasses),
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        ))->getDefinitions();
    }
}
