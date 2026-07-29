<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AppAdministrationSnippetDefinition;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\AllStoreApiSchemaMigrationScopeProvider;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\CoreStoreApiSchemaMigrationScopeProvider;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReporter;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Definition\GroupByTestDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_extensionFixtures\ExtensionDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\CustomBundleWithApiSchema\ShopwareBundleWithName;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiSchemaMigrationReporter::class)]
class StoreApiSchemaMigrationReporterTest extends TestCase
{
    private Filesystem $filesystem;

    private string $temporaryDirectory;

    #[Before]
    public function createTemporaryDirectory(): void
    {
        $this->filesystem = new Filesystem();
        $this->temporaryDirectory = sys_get_temp_dir() . '/store-api-schema-migration-reporter-test-' . bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->temporaryDirectory);
    }

    #[After]
    public function removeTemporaryDirectory(): void
    {
        $this->filesystem->remove($this->temporaryDirectory);
    }

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

        $coreReport = $reporter->report($definitions, CoreStoreApiSchemaMigrationScopeProvider::SCOPE);
        static::assertContains('GroupByTest', $coreReport->phpGeneratedOnly);
        static::assertContains('AppAdministrationSnippet', $coreReport->phpGeneratedOnly);
        static::assertNotContains('Extension', $coreReport->phpGeneratedOnly);
        static::assertNotContains('Presentation', $coreReport->jsonWithoutPhpGenerated);

        $allReport = $reporter->report($definitions, AllStoreApiSchemaMigrationScopeProvider::SCOPE);
        static::assertContains('GroupByTest', $allReport->phpGeneratedOnly);
        static::assertContains('Extension', $allReport->phpGeneratedOnly);
        static::assertContains('Presentation', $allReport->jsonWithoutPhpGenerated);
    }

    public function testReportExposesSupportedScopes(): void
    {
        static::assertSame([
            CoreStoreApiSchemaMigrationScopeProvider::SCOPE,
            AllStoreApiSchemaMigrationScopeProvider::SCOPE,
        ], $this->createReporter()->getSupportedScopes());
    }

    public function testReportFailsForUnsupportedScope(): void
    {
        $this->expectExceptionObject(ApiException::unsupportedStoreApiSchemaMigrationScope('extension', [
            CoreStoreApiSchemaMigrationScopeProvider::SCOPE,
            AllStoreApiSchemaMigrationScopeProvider::SCOPE,
        ]));

        $this->createReporter()->report([], 'extension');
    }

    public function testReportIgnoresTranslationAndVersionDefinitions(): void
    {
        $definitionSchemaBuilder = static::createMock(OpenApiDefinitionSchemaBuilder::class);
        $definitionSchemaBuilder->expects($this->never())->method('getSchemaByDefinition');

        $report = $this->createReporter(
            definitionSchemaBuilder: $definitionSchemaBuilder,
            schemaPath: $this->createSchemaPath(['components' => ['schemas' => []]]),
            allowlistPath: $this->createAllowlistPath(),
        )->report([
            'example_translation' => $this->createDefinition('example_translation'),
            'version_example' => $this->createDefinition('version_example'),
        ], AllStoreApiSchemaMigrationScopeProvider::SCOPE);

        static::assertSame([], $report->phpGeneratedOnly);
    }

    public function testReportHandlesSchemaWithoutComponents(): void
    {
        $report = $this->createReporter(
            schemaPath: $this->createSchemaPath(['paths' => []]),
            allowlistPath: $this->createAllowlistPath(),
        )->report([]);

        static::assertSame([], $report->jsonWithoutPhpGenerated);
    }

    public function testReportHandlesSchemaWithInvalidSchemasValue(): void
    {
        $report = $this->createReporter(
            schemaPath: $this->createSchemaPath(['components' => ['schemas' => 'invalid']]),
            allowlistPath: $this->createAllowlistPath(),
        )->report([]);

        static::assertSame([], $report->jsonWithoutPhpGenerated);
    }

    public function testReportUsesEmptyAllowlistWhenAllowlistFileDoesNotExist(): void
    {
        $report = $this->createReporter(
            schemaPath: $this->createSchemaPath(['components' => ['schemas' => []]]),
            allowlistPath: $this->temporaryDirectory . '/missing-allowlist.json',
        )->report([]);

        static::assertSame([], $report->phpGeneratedOnlyAllowed);
    }

    #[DataProvider('invalidAllowlistProvider')]
    public function testReportFailsForInvalidAllowlist(string $contents, string $expectedMessage): void
    {
        $allowlistPath = $this->temporaryDirectory . '/allowlist.json';
        $this->filesystem->dumpFile($allowlistPath, $contents);

        try {
            $this->createReporter(
                schemaPath: $this->createSchemaPath(['components' => ['schemas' => []]]),
                allowlistPath: $allowlistPath,
            )->report([]);

            static::fail('Expected invalid allowlist exception.');
        } catch (ApiException $exception) {
            static::assertStringContainsString($expectedMessage, $exception->getMessage());
        }
    }

    public function testReportFailsWhenAllowlistCannotBeRead(): void
    {
        $allowlistPath = $this->temporaryDirectory . '/allowlist.json';
        $filesystem = static::createMock(Filesystem::class);
        $filesystem->method('exists')->with($allowlistPath)->willReturn(true);
        $filesystem->method('readFile')->with($allowlistPath)->willThrowException(new IOException('Could not read file.', 0, null, $allowlistPath));

        $this->expectExceptionObject(ApiException::schemaDefinitionNotReadable($allowlistPath));

        $this->createReporter(
            filesystem: $filesystem,
            schemaPath: $this->createSchemaPath(['components' => ['schemas' => []]]),
            allowlistPath: $allowlistPath,
        )->report([]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidAllowlistProvider(): iterable
    {
        yield 'invalid json' => [
            '{',
            'JSON could not be decoded.',
        ];

        yield 'root value is not an object' => [
            '"invalid"',
            'The root value must be an object.',
        ];

        yield 'missing list' => [
            '{}',
            'The "jsonOverridesPhpGeneratedSchemas" list is missing.',
        ];

        yield 'non-string schema name' => [
            '{"jsonOverridesPhpGeneratedSchemas":[],"phpGeneratedStoreApiSchemas":[1]}',
            'The "phpGeneratedStoreApiSchemas" list must contain only schema names.',
        ];
    }

    private function createReporter(
        ?BundleSchemaPathCollection $bundleSchemaPathCollection = null,
        ?OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder = null,
        ?Filesystem $filesystem = null,
        ?string $schemaPath = null,
        ?string $allowlistPath = null,
    ): StoreApiSchemaMigrationReporter {
        return new StoreApiSchemaMigrationReporter(
            $definitionSchemaBuilder ?? new OpenApiDefinitionSchemaBuilder(),
            [
                new CoreStoreApiSchemaMigrationScopeProvider($schemaPath, $allowlistPath),
                new AllStoreApiSchemaMigrationScopeProvider($bundleSchemaPathCollection ?? new BundleSchemaPathCollection([]), $schemaPath, $allowlistPath),
            ],
            $filesystem ?? new Filesystem(),
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

    /**
     * @param array<string, mixed> $spec
     */
    private function createSchemaPath(array $spec): string
    {
        $schemaPath = $this->temporaryDirectory . '/schema-' . bin2hex(random_bytes(4));
        $this->filesystem->mkdir($schemaPath);
        $this->filesystem->dumpFile($schemaPath . '/schema.json', json_encode($spec, \JSON_THROW_ON_ERROR));

        return $schemaPath;
    }

    /**
     * @param list<string> $jsonOverridesPhpGeneratedSchemas
     * @param list<string> $phpGeneratedStoreApiSchemas
     */
    private function createAllowlistPath(array $jsonOverridesPhpGeneratedSchemas = [], array $phpGeneratedStoreApiSchemas = []): string
    {
        $allowlistPath = $this->temporaryDirectory . '/allowlist-' . bin2hex(random_bytes(4)) . '.json';
        $this->filesystem->dumpFile($allowlistPath, json_encode([
            'jsonOverridesPhpGeneratedSchemas' => $jsonOverridesPhpGeneratedSchemas,
            'phpGeneratedStoreApiSchemas' => $phpGeneratedStoreApiSchemas,
        ], \JSON_THROW_ON_ERROR));

        return $allowlistPath;
    }

    /**
     * @param non-empty-string $entityName
     */
    private function createDefinition(string $entityName): EntityDefinition
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        return $definition;
    }
}
