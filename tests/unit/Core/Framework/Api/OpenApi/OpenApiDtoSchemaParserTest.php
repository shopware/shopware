<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoDefinition;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoProperty;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoType;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiDtoSchemaParser::class)]
class OpenApiDtoSchemaParserTest extends TestCase
{
    /**
     * @param array<string, bool> $expectedProperties
     */
    #[DataProvider('composedRequestCases')]
    public function testComposedRequestsPreserveSiblingAndRequiredFields(string $name, array $expectedProperties): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse($this->loadSchema('../_fixtures_generator/composedRequests.json'));

        foreach ($definitions as $definition) {
            if ($definition->name !== $name) {
                continue;
            }

            $properties = [];
            foreach ($definition->properties as $property) {
                $properties[$property->name] = $property->required;
            }
            static::assertEquals($expectedProperties, $properties);

            return;
        }

        static::fail('Missing request definition: ' . $name);
    }

    /**
     * @return iterable<string, array{string, array<string, bool>}>
     */
    public static function composedRequestCases(): iterable
    {
        yield 'allOf with sibling properties and required fields' => ['CreateComposedRequest', ['baseValue' => true, 'composedValue' => true, 'siblingValue' => true, 'optionalValue' => false]];
        yield 'single allOf reference with sibling fields' => ['CreateSingleRequest', ['baseValue' => true, 'siblingValue' => true, 'optionalValue' => false]];
        yield 'inline variant with composed shared and private fields' => ['PrivateInput', ['sharedValue' => true, 'siblingShared' => true, 'privateValue' => true, 'privateSibling' => true]];
        yield 'referenced variant with composed business fields' => ['BusinessInput', ['sharedValue' => true, 'siblingShared' => true, 'businessValue' => true, 'businessSibling' => true]];
    }

    public function testRequestDependenciesPreserveOptionalPropertyPresence(): void
    {
        $schema = $this->loadSchema('../_fixtures_generator/requestDefaults.json');
        $parser = new OpenApiDtoSchemaParser();

        foreach ([$parser->parse($schema), $parser->parse($schema, includeComponentSchemas: false, namespace: 'App\\DTO'), $parser->parseComponents($schema, ['SharedDefaults'], 'App\\Shared')] as $definitions) {
            foreach ($definitions as $definition) {
                static::assertTrue($definition->preserveOptionalPropertyPresence, $definition->name);
            }
        }

        $definitions = $parser->parseComponents($schema, ['SharedDefaults'], 'App\\Shared');
        $count = $definitions[0]->properties[0];
        static::assertSame('count', $count->name);
        static::assertTrue($count->hasDefaultValue);
        static::assertSame(10, $count->defaultValue);
    }

    public function testPreservesSchemaNamesForPropertiesAndParameters(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse($this->loadSchema('parameterWireNames/schema.json'));

        static::assertCount(1, $definitions);
        $names = [];
        foreach ($definitions[0]->properties as $property) {
            $names[$property->name] = $property->schemaName;
        }
        static::assertSame(['bodyName' => 'body-name', 'camelCase' => 'camelCase', 'queryName' => 'query-name'], $names);
    }

    public function testDuplicateNormalizedPropertiesThrowException(): void
    {
        $this->expectException(FrameworkException::class);

        (new OpenApiDtoSchemaParser())->parse($this->loadSchema('invalidSchemas/duplicateProperties.json'));
    }

    public function testExtensionsPropertyIsProvidedByAbstractDto(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse($this->loadSchema('invalidSchemas/extensionsProperty.json'));

        static::assertCount(2, $definitions);
        foreach ($definitions as $definition) {
            static::assertSame(
                ['id'],
                array_map(static fn (OpenApiDtoProperty $property): string => $property->name, $definition->properties),
            );
        }
    }

    public function testDuplicateDtoNamesThrowException(): void
    {
        $this->expectExceptionObject(FrameworkException::invalidArgumentException(
            'Component schema and operation generate the same DTO name "CreateOrderRequest". Use unique names or mark the composition-only component with "x-dto-inline": true.',
        ));

        (new OpenApiDtoSchemaParser())->parse($this->loadSchema('invalidSchemas/duplicateDtoNames.json'));
    }

    public function testReferencedComponentsInheritOperationPackage(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse(
            $this->loadSchema('invalidSchemas/inheritedComponentPackage.json'),
            includeComponentSchemas: false,
        );

        $packages = [];
        foreach ($definitions as $definition) {
            $packages[$definition->name] = $definition->package;
        }

        static::assertSame('checkout', $packages['CreateOrderResponse']);
        static::assertSame('checkout', $packages['OrderEnvelope']);
        static::assertSame('checkout', $packages['Order']);
    }

    public function testExplicitComponentPackageOverridesInheritedPackage(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse(
            $this->loadSchema('invalidSchemas/explicitComponentPackage.json'),
            includeComponentSchemas: false,
        );

        $packages = [];
        foreach ($definitions as $definition) {
            $packages[$definition->name] = $definition->package;
        }

        static::assertSame('checkout', $packages['CreateOrderResponse']);
        static::assertSame('checkout', $packages['OrderEnvelope']);
        static::assertSame('framework', $packages['Order']);
        static::assertSame('framework', $packages['OrderDetails']);
    }

    public function testConflictingInheritedComponentPackagesThrowException(): void
    {
        $this->expectExceptionObject(FrameworkException::invalidArgumentException(
            'Component schema "Order" inherits conflicting DTO packages "checkout" and "framework". Set "x-dto-package" explicitly on the component.',
        ));

        (new OpenApiDtoSchemaParser())->parse(
            $this->loadSchema('invalidSchemas/conflictingInheritedComponentPackages.json'),
            includeComponentSchemas: false,
        );
    }

    public function testComponentReferencedByResponseIsClassifiedAsResponse(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parseComponents($this->loadSchema('responseComponent/schema.json'), ['SuccessResponse'], 'App\\Api');

        static::assertCount(1, $definitions);
        static::assertSame(OpenApiDtoType::Response, $definitions[0]->type);
    }

    public function testReferencedRequestBodyGeneratesDependenciesWithoutComponentSchemas(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse($this->loadSchema('referencedRequestBody/schema.json'), includeComponentSchemas: false);

        static::assertEqualsCanonicalizing(
            ['ReadNewsletterRecipientRequest', 'Criteria', 'Sort', 'SortOptions'],
            array_map(static fn (OpenApiDtoDefinition $definition): string => $definition->name, $definitions),
        );
    }

    public function testSchemaVariantsGenerateDependenciesWithoutInlineSchemas(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse($this->loadSchema('schemaUnions/schema.json'), includeComponentSchemas: false);

        $names = array_map(static fn (OpenApiDtoDefinition $definition): string => $definition->name, $definitions);
        static::assertContains('Criteria', $names);
        static::assertContains('EqualsFilter', $names);
        static::assertContains('RangeFilter', $names);
        static::assertContains('AverageAggregation', $names);
        static::assertContains('NestedCountAggregation', $names);
        static::assertNotContains('SubAggregations', $names);
    }

    public function testOpenApiFixturesProduceAdjacentDtoDefinitions(): void
    {
        $parser = new OpenApiDtoSchemaParser();
        $filesystem = new Filesystem();

        foreach (Finder::create()->directories()->depth(0)->exclude('invalidSchemas')->sortByName()->in(__DIR__ . '/_fixtures') as $fixtureDirectory) {
            foreach (Finder::create()->files()->name('*.json')->sortByName()->in($fixtureDirectory->getPathname()) as $schemaFile) {
                $schema = json_decode($filesystem->readFile($schemaFile->getPathname()), true, flags: \JSON_THROW_ON_ERROR);
                static::assertIsArray($schema);

                $definitions = $parser->parse($schema);
                static::assertNotSame([], $definitions, $schemaFile->getPathname());

                $definitionNames = array_map(static fn (OpenApiDtoDefinition $definition): string => $definition->name, $definitions);
                $fixtureNames = [];
                foreach (Finder::create()->files()->name('*.php')->sortByName()->in($fixtureDirectory->getPathname()) as $fixtureFile) {
                    $fixtureNames[] = $fixtureFile->getBasename('.php');
                }

                sort($definitionNames);
                sort($fixtureNames);
                static::assertSame($fixtureNames, $definitionNames, $schemaFile->getPathname());
                static::assertCount(\count($definitionNames), array_unique($definitionNames), $schemaFile->getPathname());

                foreach ($definitions as $definition) {
                    static::assertNotSame('', $definition->name);
                    foreach ($definition->properties as $property) {
                        static::assertNotSame('', $property->name);
                        static::assertNotSame('', $property->phpType);
                    }
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSchema(string $fixture): array
    {
        $schema = json_decode((new Filesystem())->readFile(__DIR__ . '/_fixtures/' . $fixture), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($schema);

        return $schema;
    }
}
