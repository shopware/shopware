<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use App\DTO\ConstValues;
use App\DTO\Presence;
use App\DTO\WireNames;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoDefinition;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerator;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoType;
use Shopware\Core\Framework\Api\Serializer\DtoNormalizer;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiDtoClassRenderer::class)]
class OpenApiDtoClassRendererTest extends TestCase
{
    public function testGeneratedConstantsValidateWithoutDefaults(): void
    {
        require_once __DIR__ . '/_fixtures/constants/ConstValues.php';
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $dto = new ConstValues('fixed');

        static::assertSame(['kind' => 'fixed'], get_object_vars($dto));
        static::assertCount(0, $validator->validate($dto));

        $dto->optionalKind = 'fixed';
        $dto->enabled = false;
        $dto->count = 0;
        $dto->ratio = 1.0;
        $dto->quoted = 'it\'s\\fixed';
        static::assertCount(0, $validator->validate($dto));

        $dto->kind = 'wrong';
        $dto->optionalKind = 'wrong';
        $dto->enabled = true;
        $dto->count = 1;
        $dto->ratio = 2.0;
        $dto->quoted = 'wrong';
        static::assertCount(6, $validator->validate($dto));
    }

    public function testOnlyRequiredPropertiesArePromotedConstructorParameters(): void
    {
        require_once __DIR__ . '/_fixtures/presence/Presence.php';
        $reflection = new \ReflectionClass(Presence::class);

        foreach ($reflection->getProperties() as $property) {
            static::assertSame(
                \in_array($property->getName(), ['requiredValue', 'requiredNullable'], true),
                $property->isPromoted(),
                $property->getName(),
            );
        }

        $constructor = $reflection->getConstructor();
        static::assertNotNull($constructor);
        static::assertSame(
            ['requiredValue', 'requiredNullable'],
            array_map(static fn (\ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()),
        );
        static::assertSame(2, $constructor->getNumberOfRequiredParameters());
    }

    public function testGeneratedWireNamesWorkWithSerializer(): void
    {
        require_once __DIR__ . '/_fixtures/wire-names/WireNames.php';

        $metadata = new ClassMetadataFactory(new AttributeLoader());
        $serializer = new Serializer([new DtoNormalizer(new PropertyNormalizer($metadata, new MetadataAwareNameConverter($metadata)))], [new JsonEncoder()]);
        $data = [
            'total-count-mode' => 2,
            'post-filter' => 'filter',
            'camelCase' => 'unchanged',
            'snake_case' => 'snake',
            'quote\'field' => 'quoted',
        ];

        $dto = $serializer->denormalize($data, WireNames::class);
        static::assertInstanceOf(WireNames::class, $dto);
        static::assertSame(2, $dto->totalCountMode);
        static::assertSame('filter', $dto->postFilter);
        static::assertJsonStringEqualsJsonString(json_encode($data, \JSON_THROW_ON_ERROR), $serializer->serialize($dto, 'json'));
    }

    public function testNativeEnumDefinitionIsRenderedAsBackedEnum(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'components' => [
                'schemas' => [
                    'NewsletterStatus' => [
                        'x-dto-namespace' => 'App\\DTO',
                        'type' => 'string',
                        'enum' => ['notSet', 'optIn'],
                    ],
                ],
            ],
        ]);

        $rendered = $this->renderDefinition($this->definitionByName($definitions, 'NewsletterStatus'));

        static::assertStringContainsString('enum NewsletterStatus', $rendered);
        static::assertStringContainsString('case NOT_SET = \'notSet\';', $rendered);
        static::assertStringContainsString('case OPT_IN = \'optIn\';', $rendered);
    }

    public function testDefaultResponseStatusCallsParentConstructor(): void
    {
        $response = $this->renderDefinition(new OpenApiDtoDefinition(
            name: 'ReadNewsletterRecipientResponse',
            properties: [],
            type: OpenApiDtoType::Response,
        ));

        static::assertStringContainsString('parent::__construct();', $response);
        static::assertStringNotContainsString('use Symfony\\Component\\HttpFoundation\\Response;', $response);
    }

    public function testEnumValuesAreEscaped(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'components' => [
                'schemas' => [
                    'SpecialValue' => [
                        'x-dto-namespace' => 'App\\DTO',
                        'type' => 'string',
                        'enum' => ['foo\'\\bar', 'foo-bar'],
                    ],
                ],
            ],
        ]);

        $rendered = $this->renderDefinition($this->definitionByName($definitions, 'SpecialValue'));

        static::assertStringContainsString('case FOO__BAR = \'foo\\\'\\\\bar\';', $rendered);
        static::assertStringContainsString('case FOO_BAR = \'foo-bar\';', $rendered);
    }

    public function testEnumCaseNameCollisionsThrowException(): void
    {
        $this->expectException(FrameworkException::class);

        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'components' => [
                'schemas' => [
                    'CollidingValue' => [
                        'x-dto-namespace' => 'App\\DTO',
                        'type' => 'string',
                        'enum' => ['foo-bar', 'foo_bar'],
                    ],
                ],
            ],
        ]);

        $this->renderDefinition($this->definitionByName($definitions, 'CollidingValue'));
    }

    public function testEnumValuesWithoutCaseNameThrowException(): void
    {
        $this->expectException(FrameworkException::class);

        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'components' => [
                'schemas' => [
                    'InvalidValue' => [
                        'x-dto-namespace' => 'App\\DTO',
                        'type' => 'string',
                        'enum' => [''],
                    ],
                ],
            ],
        ]);

        $this->renderDefinition($this->definitionByName($definitions, 'InvalidValue'));
    }

    public function testOpenApiFixturesMatchGeneratedDtos(): void
    {
        $parser = new OpenApiDtoSchemaParser();
        $renderer = new OpenApiDtoClassRenderer(new MockClock('2026-07-07 00:00:00'));
        $filesystem = new Filesystem();
        $fixtureDirectories = Finder::create()
            ->directories()
            ->depth(0)
            ->sortByName()
            ->in(__DIR__ . '/_fixtures');

        foreach ($fixtureDirectories as $fixtureDirectory) {
            $schemaFiles = Finder::create()
                ->files()
                ->name('*.json')
                ->sortByName()
                ->in($fixtureDirectory->getPathname());

            foreach ($schemaFiles as $schemaFile) {
                $schema = json_decode($filesystem->readFile($schemaFile->getPathname()), true, flags: \JSON_THROW_ON_ERROR);
                static::assertIsArray($schema);

                foreach ($parser->parse($schema) as $definition) {
                    $generated = $renderer->renderClass($definition, 'App\\DTO');

                    $generatedFile = $fixtureDirectory->getPathname() . '/' . $definition->name . '.php';
                    static::assertSame($filesystem->readFile($generatedFile), $generated);
                }
            }
        }
    }

    public function testReferencedScalarEnumPropertiesAreRenderedAsNativeTypesWithChoiceConstraint(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'openapi' => '3.1.0',
            'info' => [],
            'paths' => [
                '/account/newsletter-recipient' => [
                    'get' => [
                        'operationId' => 'readNewsletterRecipient',
                        'responses' => [
                            '201' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'required' => ['status', 'priority'],
                                            'properties' => [
                                                'status' => [
                                                    '$ref' => '#/components/schemas/NewsletterStatus',
                                                ],
                                                'priority' => [
                                                    '$ref' => '#/components/schemas/Priority',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'Priority' => [
                        'type' => 'number',
                        'enum' => [0.5, 10.5, 20.5],
                    ],
                ],
            ],
        ]);

        $response = $this->renderDefinition($this->definitionByName($definitions, 'ReadNewsletterRecipientResponse'));

        static::assertStringContainsString('use Shopware\\Core\\Framework\\Api\\Response\\AbstractResponse;', $response);
        static::assertStringContainsString('final class ReadNewsletterRecipientResponse extends AbstractResponse', $response);
        static::assertStringContainsString('use Symfony\\Component\\HttpFoundation\\Response;', $response);
        static::assertStringContainsString('parent::__construct(statusCode: Response::HTTP_CREATED);', $response);
        static::assertStringContainsString('#[Assert\Choice(choices: [0.5, 10.5, 20.5])]', $response);
        static::assertStringContainsString('public float $priority,', $response);
        static::assertStringNotContainsString('public NewsletterStatus $status,', $response);
        static::assertStringNotContainsString('public Priority $priority,', $response);
        static::assertStringNotContainsString('#[Assert\Valid]', $response);
    }

    public function testNativeEnumDefaultIsRenderedAsEnumCase(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'components' => [
                'schemas' => [
                    'Criteria' => [
                        'type' => 'object',
                        'properties' => [
                            'totalCountMode' => [
                                '$ref' => '#/components/schemas/TotalCountMode',
                            ],
                        ],
                    ],
                    'TotalCountMode' => [
                        'x-dto-namespace' => 'App\\DTO',
                        'type' => 'string',
                        'enum' => ['none', 'exact'],
                        'default' => 'none',
                    ],
                ],
            ],
        ]);

        $rendered = $this->renderDefinition($this->definitionByName($definitions, 'Criteria'));

        static::assertStringContainsString('public TotalCountMode $totalCountMode = TotalCountMode::NONE;', $rendered);
    }

    public function testRequiredPropertyRemainsRequiredWithSchemaConst(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'openapi' => '3.1.0',
            'info' => [],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Response' => [
                        'type' => 'object',
                        'properties' => [
                            'apiAlias' => [
                                'type' => 'string',
                                'const' => 'account_newsletter_recipient',
                            ],
                        ],
                        'required' => ['apiAlias'],
                    ],
                ],
            ],
        ]);

        $response = $this->renderDefinition($this->definitionByName($definitions, 'Response'));

        static::assertStringContainsString('public string $apiAlias,', $response);
        static::assertStringContainsString('#[Assert\\IdenticalTo(value: \'account_newsletter_recipient\')]', $response);
    }

    public function testReferencedMapSchemasAreRenderedAsTypedArrays(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'openapi' => '3.1.0',
            'info' => [],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Criteria' => [
                        'type' => 'object',
                        'properties' => [
                            'associations' => [
                                '$ref' => '#/components/schemas/Associations',
                            ],
                            'includes' => [
                                '$ref' => '#/components/schemas/Includes',
                            ],
                        ],
                    ],
                    'Associations' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            '$ref' => '#/components/schemas/Criteria',
                        ],
                    ],
                    'Includes' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ]);

        $criteria = $this->renderDefinition($this->definitionByName($definitions, 'Criteria'));

        static::assertStringContainsString('@var array<string, Criteria>', $criteria);
        static::assertStringContainsString('public array $associations;', $criteria);
        static::assertStringContainsString('@var array<string, list<string>>', $criteria);
        static::assertStringContainsString('public array $includes;', $criteria);
        static::assertStringNotContainsString('public ?Associations $associations = null,', $criteria);
        static::assertSame(1, substr_count($criteria, '#[Assert\\Valid]'));
    }

    public function testSingleReferencedRequestBodyUsesComponentDtoAndGeneratesDependencies(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'openapi' => '3.1.0',
            'info' => [],
            'paths' => [
                '/newsletter-recipient' => [
                    'post' => [
                        'operationId' => 'readNewsletterRecipient',
                        'description' => 'Read newsletter recipients.',
                        'requestBody' => [
                            'required' => false,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'allOf' => [
                                            ['$ref' => '#/components/schemas/Criteria'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'Criteria' => [
                        'type' => 'object',
                        'properties' => [
                            'sort' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/Sort'],
                            ],
                        ],
                    ],
                    'Sort' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'options' => [
                                'type' => 'object',
                                'properties' => [
                                    'natural' => ['type' => 'boolean'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], includeComponentSchemas: false);

        $request = $this->renderDefinition($this->definitionByName($definitions, 'ReadNewsletterRecipientRequest'));

        static::assertStringContainsString('#[Assert\\Valid]', $request);
        static::assertStringContainsString('public Criteria $criteria;', $request);
        static::assertStringNotContainsString('public ?array $sort = null,', $request);
        static::assertSame('Criteria', $this->definitionByName($definitions, 'Criteria')->name);
        static::assertSame('Sort', $this->definitionByName($definitions, 'Sort')->name);
        static::assertSame('SortOptions', $this->definitionByName($definitions, 'SortOptions')->name);
    }

    public function testNestedDtosInheritPackageFromParentSchema(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'openapi' => '3.1.0',
            'info' => [],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'RangeFilter' => [
                        OpenApiDtoGenerator::PACKAGE_EXTENSION => 'framework',
                        'type' => 'object',
                        'properties' => [
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'gte' => [
                                        'type' => 'number',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $parameters = $this->renderDefinition($this->definitionByName($definitions, 'RangeFilterParameters'));

        static::assertStringContainsString('use Shopware\\Core\\Framework\\Log\\Package;', $parameters);
        static::assertStringContainsString('#[Package(\'framework\')]', $parameters);
    }

    public function testSchemaVariantsAreRenderedAsUnionTypes(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'openapi' => '3.1.0',
            'info' => [],
            'paths' => [
                '/search' => [
                    'post' => [
                        'operationId' => 'search',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'allOf' => [
                                            ['$ref' => '#/components/schemas/Criteria'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'Criteria' => [
                        'type' => 'object',
                        'properties' => [
                            'filter' => [
                                'type' => 'array',
                                'items' => [
                                    'anyOf' => [
                                        ['$ref' => '#/components/schemas/EqualsFilter'],
                                        ['$ref' => '#/components/schemas/RangeFilter'],
                                    ],
                                ],
                            ],
                            'query' => [
                                'oneOf' => [
                                    ['$ref' => '#/components/schemas/EqualsFilter'],
                                    ['$ref' => '#/components/schemas/RangeFilter'],
                                    ['type' => 'null'],
                                ],
                            ],
                            'aggregations' => [
                                'type' => 'array',
                                'items' => [
                                    '$ref' => [
                                        '#/components/schemas/Aggregation',
                                        '#/components/schemas/Aggregation',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'EqualsFilter' => [
                        'type' => 'object',
                        'properties' => ['field' => ['type' => 'string']],
                    ],
                    'RangeFilter' => [
                        'type' => 'object',
                        'properties' => ['field' => ['type' => 'string']],
                    ],
                    'Aggregation' => [
                        'anyOf' => [
                            ['$ref' => '#/components/schemas/AverageAggregation'],
                            [
                                'title' => 'NestedCountAggregation',
                                'allOf' => [
                                    ['$ref' => '#/components/schemas/CountAggregation'],
                                    ['$ref' => '#/components/schemas/SubAggregations'],
                                ],
                            ],
                        ],
                    ],
                    'AverageAggregation' => [
                        'type' => 'object',
                        'properties' => ['field' => ['type' => 'string']],
                    ],
                    'CountAggregation' => [
                        'type' => 'object',
                        'properties' => ['field' => ['type' => 'string']],
                    ],
                    'SubAggregations' => [
                        OpenApiDtoGenerator::INLINE_EXTENSION => true,
                        'type' => 'object',
                        'properties' => [
                            'aggregation' => ['$ref' => '#/components/schemas/AverageAggregation'],
                        ],
                    ],
                ],
            ],
        ], includeComponentSchemas: false);

        $criteria = $this->renderDefinition($this->definitionByName($definitions, 'Criteria'));
        $nestedCountAggregation = $this->renderDefinition($this->definitionByName($definitions, 'NestedCountAggregation'));

        static::assertStringContainsString('@var list<EqualsFilter|RangeFilter>', $criteria);
        static::assertStringContainsString('public array $filter;', $criteria);
        static::assertStringContainsString('public EqualsFilter|RangeFilter|null $query;', $criteria);
        static::assertStringContainsString('@var list<AverageAggregation|NestedCountAggregation>', $criteria);
        static::assertSame(3, substr_count($criteria, '#[Assert\\Valid]'));
        static::assertSame('AverageAggregation', $this->definitionByName($definitions, 'AverageAggregation')->name);
        static::assertStringContainsString('public string $field;', $nestedCountAggregation);
        static::assertStringContainsString('public AverageAggregation $aggregation;', $nestedCountAggregation);
        static::assertNotContains('SubAggregations', array_map(
            static fn (OpenApiDtoDefinition $definition): string => $definition->name,
            $definitions,
        ));
    }

    /**
     * @param list<OpenApiDtoDefinition> $definitions
     */
    private function definitionByName(array $definitions, string $name): OpenApiDtoDefinition
    {
        foreach ($definitions as $definition) {
            if ($definition->name === $name) {
                return $definition;
            }
        }

        static::fail(\sprintf('Definition "%s" was not generated.', $name));
    }

    private function renderDefinition(OpenApiDtoDefinition $definition): string
    {
        return (new OpenApiDtoClassRenderer(new MockClock('2026-07-14')))->renderClass($definition, 'Shopware\\Core\\Framework\\Api\\Dto');
    }
}
