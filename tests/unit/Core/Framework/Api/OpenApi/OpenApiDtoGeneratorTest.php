<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoDefinition;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGeneratedFile;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerationCheckResult;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerationResult;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoGenerator;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoProperty;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(OpenApiDtoGenerator::class)]
#[CoversClass(OpenApiDtoSchemaParser::class)]
#[CoversClass(OpenApiDtoClassRenderer::class)]
#[CoversClass(OpenApiDtoDefinition::class)]
#[CoversClass(OpenApiDtoProperty::class)]
#[CoversClass(OpenApiDtoGeneratedFile::class)]
#[CoversClass(OpenApiDtoGenerationResult::class)]
#[CoversClass(OpenApiDtoGenerationCheckResult::class)]
class OpenApiDtoGeneratorTest extends TestCase
{
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
                            '200' => [
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
                    'NewsletterStatus' => [
                        'type' => 'string',
                        'enum' => ['notSet', 'optIn', 'optOut', 'direct', 'undefined'],
                    ],
                    'Priority' => [
                        'type' => 'integer',
                        'enum' => [0, 10, 20],
                    ],
                ],
            ],
        ]);

        $response = $this->renderDefinition($this->definitionByName($definitions, 'ReadNewsletterRecipientResponse'));

        static::assertStringContainsString('#[Assert\\Choice(choices: [\'notSet\', \'optIn\', \'optOut\', \'direct\', \'undefined\'])]', $response);
        static::assertStringContainsString('public string $status,', $response);
        static::assertStringContainsString('#[Assert\Choice(choices: [0, 10, 20])]', $response);
        static::assertStringContainsString('public int $priority,', $response);
        static::assertStringNotContainsString('public NewsletterStatus $status,', $response);
        static::assertStringNotContainsString('public Priority $priority,', $response);
        static::assertStringNotContainsString('#[Assert\Valid]', $response);
    }

    public function testReferencedScalarEnumParametersAreRenderedAsNativeTypesWithChoiceConstraint(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse([
            'openapi' => '3.1.0',
            'info' => [],
            'paths' => [
                '/products' => [
                    'get' => [
                        'operationId' => 'readProducts',
                        'parameters' => [
                            [
                                'name' => 'availability',
                                'in' => 'query',
                                'description' => 'Availability filter',
                                'schema' => [
                                    '$ref' => '#/components/schemas/Availability',
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Products',
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'Availability' => [
                        'type' => 'string',
                        'enum' => ['all', 'available'],
                    ],
                ],
            ],
        ]);

        $request = $this->renderDefinition($this->definitionByName($definitions, 'ReadProductsRequest'));

        static::assertStringContainsString('Availability filter', $request);
        static::assertStringContainsString('#[Assert\\Choice(choices: [\'all\', \'available\'])]', $request);
        static::assertStringContainsString('public ?string $availability = null,', $request);
        static::assertStringNotContainsString('public ?Availability $availability = null,', $request);
        static::assertStringNotContainsString('#[Assert\Valid]', $request);
    }

    public function testReferencedMapSchemasAreRenderedAsArrays(): void
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
                        ],
                    ],
                    'Associations' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            '$ref' => '#/components/schemas/Criteria',
                        ],
                    ],
                ],
            ],
        ]);

        $criteria = $this->renderDefinition($this->definitionByName($definitions, 'Criteria'));

        static::assertStringContainsString('@var array<string, mixed>', $criteria);
        static::assertStringContainsString('public ?array $associations = null,', $criteria);
        static::assertStringNotContainsString('public ?Associations $associations = null,', $criteria);
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
