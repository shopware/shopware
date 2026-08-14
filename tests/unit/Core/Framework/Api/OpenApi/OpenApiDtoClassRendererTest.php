<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoDefinition;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerator;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiDtoClassRenderer::class)]
class OpenApiDtoClassRendererTest extends TestCase
{
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

        static::assertStringContainsString('public TotalCountMode $totalCountMode = TotalCountMode::NONE,', $rendered);
    }

    public function testStringConstIsRenderedAsPropertyDefault(): void
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

        static::assertStringContainsString('public string $apiAlias = \'account_newsletter_recipient\',', $response);
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
        static::assertStringContainsString('public ?array $associations = null,', $criteria);
        static::assertStringContainsString('@var array<string, list<string>>', $criteria);
        static::assertStringContainsString('public ?array $includes = null,', $criteria);
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
        static::assertStringContainsString('public ?Criteria $criteria = null,', $request);
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
        static::assertStringContainsString('public ?array $filter = null,', $criteria);
        static::assertStringContainsString('public EqualsFilter|RangeFilter|null $query = null,', $criteria);
        static::assertStringContainsString('@var list<AverageAggregation|NestedCountAggregation>', $criteria);
        static::assertSame(3, substr_count($criteria, '#[Assert\\Valid]'));
        static::assertSame('AverageAggregation', $this->definitionByName($definitions, 'AverageAggregation')->name);
        static::assertStringContainsString('public ?string $field = null,', $nestedCountAggregation);
        static::assertStringContainsString('public ?AverageAggregation $aggregation = null,', $nestedCountAggregation);
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
