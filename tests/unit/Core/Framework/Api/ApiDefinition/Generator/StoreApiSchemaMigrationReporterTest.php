<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use OpenApi\Annotations\Schema;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
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
use Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SalesChannelSimpleDefinition;
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
        $report = $this->createReporter(
            schemaPath: $this->createSchemaPath([
                'paths' => $this->createPathReferencingSchemas('GroupByTest', 'AclRole'),
                'components' => [
                    'schemas' => [
                        'CalculatedPrice' => ['type' => 'object'],
                    ],
                ],
            ]),
        )->report($this->createDefinitions());

        static::assertContains('GroupByTest', $report->phpGeneratedOnly);
        static::assertContains('AclRole', $report->phpGeneratedOnly);
        static::assertContains('CalculatedPrice', $report->jsonWithoutPhpGenerated);
        static::assertSame([], $report->jsonOverridesPhpGenerated);
    }

    public function testReportTracksTransitivelyReferencedPhpGeneratedSchemas(): void
    {
        $definitionSchemaBuilder = static::createStub(OpenApiDefinitionSchemaBuilder::class);
        $definitionSchemaBuilder->method('getSchemaName')->willReturnCallback(static function (EntityDefinition $definition): string {
            return match ($definition->getEntityName()) {
                'parent' => 'Parent',
                'child' => 'Child',
                'unused' => 'Unused',
                default => throw new \LogicException('Unexpected definition.'),
            };
        });
        $definitionSchemaBuilder->method('getSchemaByDefinition')->willReturnCallback(static function (EntityDefinition $definition): array {
            return match ($definition->getEntityName()) {
                'parent' => [
                    'Parent' => new Schema([
                        'schema' => 'Parent',
                        'ref' => '#/components/schemas/Child',
                    ]),
                ],
                'child' => [
                    'Child' => new Schema([
                        'schema' => 'Child',
                        'type' => 'object',
                    ]),
                ],
                'unused' => [
                    'Unused' => new Schema([
                        'schema' => 'Unused',
                        'type' => 'object',
                    ]),
                ],
                default => throw new \LogicException('Unexpected definition.'),
            };
        });
        $definitionSchemaBuilder->method('getExtensionSchemaByDefinition')->willReturn([]);

        $report = $this->createReporter(
            definitionSchemaBuilder: $definitionSchemaBuilder,
            schemaPath: $this->createSchemaPath([
                'paths' => $this->createPathReferencingSchemas('Parent'),
                'components' => ['schemas' => []],
            ]),
        )->report([
            'parent' => $this->createDefinition('parent'),
            'child' => $this->createDefinition('child'),
            'unused' => $this->createDefinition('unused'),
        ], AllStoreApiSchemaMigrationScopeProvider::SCOPE);

        static::assertContains('Parent', $report->phpGeneratedOnly);
        static::assertContains('Child', $report->phpGeneratedOnly);
        static::assertNotContains('Unused', $report->phpGeneratedOnly);
    }

    public function testJsonSchemaWithPhpDefinitionIsTreatedAsJsonOnly(): void
    {
        $report = $this->createReporter(
            schemaPath: $this->createSchemaPath([
                'paths' => $this->createPathReferencingSchemas('GroupByTest'),
                'components' => [
                    'schemas' => [
                        'GroupByTest' => ['type' => 'object'],
                    ],
                ],
            ]),
        )->report($this->createDefinitions());

        static::assertContains('GroupByTest', $report->jsonWithoutPhpGenerated);
        static::assertNotContains('GroupByTest', $report->phpGeneratedOnly);
        static::assertSame([], $report->jsonOverridesPhpGenerated);
        static::assertFalse($report->hasMismatches());
    }

    public function testReportTracksLegacyJsonApiSchemaForPhpOwnedDefinition(): void
    {
        $report = $this->createReporter(
            schemaPath: $this->createSchemaPath([
                'paths' => $this->createPathReferencingSchemas('Simple'),
                'components' => ['schemas' => []],
            ]),
        )->report(
            $this->createDefinitions([SalesChannelSimpleDefinition::class]),
            AllStoreApiSchemaMigrationScopeProvider::SCOPE,
        );

        static::assertContains('Simple', $report->phpGeneratedOnly);
        static::assertContains('SimpleJsonApi', $report->phpGeneratedOnly);
    }

    public function testReportStillDetectsUnexpectedPhpGeneratedJsonSchemaOverlap(): void
    {
        $definitionSchemaBuilder = static::createStub(OpenApiDefinitionSchemaBuilder::class);
        $definitionSchemaBuilder->method('getSchemaName')->willReturn('DifferentSchema');
        $definitionSchemaBuilder->method('getSchemaByDefinition')->willReturn([
            'GroupByTest' => new Schema(['schema' => 'GroupByTest', 'type' => 'object']),
        ]);

        $report = $this->createReporter(
            definitionSchemaBuilder: $definitionSchemaBuilder,
            schemaPath: $this->createSchemaPath([
                'paths' => $this->createPathReferencingSchemas('GroupByTest'),
                'components' => [
                    'schemas' => [
                        'GroupByTest' => ['type' => 'object'],
                    ],
                ],
            ]),
        )->report([
            'group_by_test' => $this->createDefinition('group_by_test'),
        ], AllStoreApiSchemaMigrationScopeProvider::SCOPE);

        static::assertSame(['GroupByTest'], $report->jsonOverridesPhpGenerated);
        static::assertTrue($report->hasMismatches());
    }

    public function testCoreScopeIgnoresExtensionDefinitionsAndSchemaFiles(): void
    {
        $reporter = $this->createReporter(
            new BundleSchemaPathCollection([new ShopwareBundleWithName()]),
            schemaPath: $this->createSchemaPath([
                'paths' => $this->createPathReferencingSchemas('GroupByTest', 'AclRole', 'Extension'),
                'components' => ['schemas' => []],
            ]),
        );
        $definitions = $this->createDefinitions([ExtensionDefinition::class]);

        $coreReport = $reporter->report($definitions, CoreStoreApiSchemaMigrationScopeProvider::SCOPE);
        static::assertContains('GroupByTest', $coreReport->phpGeneratedOnly);
        static::assertContains('AclRole', $coreReport->phpGeneratedOnly);
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
        )->report([]);

        static::assertSame([], $report->jsonWithoutPhpGenerated);
    }

    public function testReportHandlesSchemaWithInvalidSchemasValue(): void
    {
        $report = $this->createReporter(
            schemaPath: $this->createSchemaPath(['components' => ['schemas' => 'invalid']]),
        )->report([]);

        static::assertSame([], $report->jsonWithoutPhpGenerated);
    }

    private function createReporter(
        ?BundleSchemaPathCollection $bundleSchemaPathCollection = null,
        ?OpenApiDefinitionSchemaBuilder $definitionSchemaBuilder = null,
        ?string $schemaPath = null,
    ): StoreApiSchemaMigrationReporter {
        return new StoreApiSchemaMigrationReporter(
            $definitionSchemaBuilder ?? new OpenApiDefinitionSchemaBuilder(),
            [
                new CoreStoreApiSchemaMigrationScopeProvider($schemaPath),
                new AllStoreApiSchemaMigrationScopeProvider($bundleSchemaPathCollection ?? new BundleSchemaPathCollection([]), $schemaPath),
            ],
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
                AclRoleDefinition::class,
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
     * @return array<string, mixed>
     */
    private function createPathReferencingSchemas(string ...$schemaNames): array
    {
        return [
            '/test' => [
                'get' => [
                    'responses' => [
                        '200' => [
                            'description' => 'OK',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'oneOf' => array_map(
                                            static fn (string $schemaName): array => ['$ref' => '#/components/schemas/' . $schemaName],
                                            $schemaNames
                                        ),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
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
