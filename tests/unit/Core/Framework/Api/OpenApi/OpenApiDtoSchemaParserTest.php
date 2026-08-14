<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoDefinition;
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
    public function testDuplicateNormalizedPropertiesThrowException(): void
    {
        $this->expectException(FrameworkException::class);

        (new OpenApiDtoSchemaParser())->parse([
            'paths' => [
                '/duplicate-properties' => [
                    'post' => [
                        'operationId' => 'duplicateProperties',
                        'parameters' => [
                            ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'foo-bar', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'fooBar', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ],
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'limit' => ['type' => 'integer'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testComponentReferencedByResponseIsClassifiedAsResponse(): void
    {
        $definitions = (new OpenApiDtoSchemaParser())->parseComponents([
            'paths' => [
                '/success' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/SuccessResponse'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'SuccessResponse' => [
                        'type' => 'object',
                        'properties' => ['success' => ['type' => 'boolean']],
                    ],
                ],
            ],
        ], ['SuccessResponse'], 'App\\Api');

        static::assertCount(1, $definitions);
        static::assertSame(OpenApiDtoType::Response, $definitions[0]->type);
    }

    public function testOpenApiFixturesProduceAdjacentDtoDefinitions(): void
    {
        $parser = new OpenApiDtoSchemaParser();
        $filesystem = new Filesystem();

        foreach (Finder::create()->directories()->depth(0)->sortByName()->in(__DIR__ . '/_fixtures') as $fixtureDirectory) {
            foreach (Finder::create()->files()->name('*.json')->sortByName()->in($fixtureDirectory->getPathname()) as $schemaFile) {
                $schema = json_decode($filesystem->readFile($schemaFile->getPathname()), true, flags: \JSON_THROW_ON_ERROR);
                static::assertIsArray($schema);

                $definitions = $parser->parse($schema);
                static::assertNotEmpty($definitions, $schemaFile->getPathname());

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
}
