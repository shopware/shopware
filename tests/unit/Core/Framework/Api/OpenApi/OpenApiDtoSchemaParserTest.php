<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
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
    public function testOpenApiFixturesProduceAdjacentDtoDefinitions(): void
    {
        $parser = new OpenApiDtoSchemaParser();
        $filesystem = new Filesystem();

        foreach (Finder::create()->directories()->depth(0)->sortByName()->in(__DIR__ . '/_fixtures') as $fixtureDirectory) {
            foreach (Finder::create()->files()->name('*.json')->sortByName()->in($fixtureDirectory->getPathname()) as $schemaFile) {
                $schema = json_decode($filesystem->readFile($schemaFile->getPathname()), true, flags: \JSON_THROW_ON_ERROR);
                static::assertIsArray($schema);

                foreach ($parser->parse($schema) as $definition) {
                    static::assertFileExists($fixtureDirectory->getPathname() . '/' . $definition->name . '.php');
                }
            }
        }
    }
}
