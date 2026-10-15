<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoDefinition;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
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
    public function testCyclicRequestBodyReferenceThrowsException(): void
    {
        $schema = $this->loadSchema('invalidSchemas/cyclicRequestBody.json');
        $this->expectExceptionObject(FrameworkException::invalidArgumentException('Cannot resolve request reference "#/components/requestBodies/InputAlias".'));

        (new OpenApiDtoSchemaParser())->parse($schema);
    }

    public function testDuplicateNormalizedPropertiesThrowException(): void
    {
        $this->expectException(FrameworkException::class);

        (new OpenApiDtoSchemaParser())->parse($this->loadSchema('invalidSchemas/duplicateProperties.json'));
    }

    public function testDuplicateDtoNamesThrowException(): void
    {
        $this->expectExceptionObject(FrameworkException::invalidArgumentException(
            'Component schema and operation generate the same DTO name "CreateOrderRequest". Use unique names or mark the composition-only component with "x-dto-inline": true.',
        ));

        (new OpenApiDtoSchemaParser())->parse($this->loadSchema('invalidSchemas/duplicateDtoNames.json'));
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

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function openApiFixtures(): iterable
    {
        foreach (Finder::create()->files()->name('*.json')->notPath('invalidSchemas')->sortByName()->in(__DIR__ . '/_fixtures') as $schemaFile) {
            yield $schemaFile->getRelativePathname() => [$schemaFile->getPathname(), true];
            if (\in_array($schemaFile->getRelativePathname(), ['requestDefaults/schema.json', 'referencedInputs/schema.json', 'referencedRequestBody/schema.json', 'schemaUnions/schema.json'], true)) {
                yield $schemaFile->getRelativePathname() . ' (referenced components only)' => [$schemaFile->getPathname(), false];
            }
        }
    }

    #[DataProvider('openApiFixtures')]
    public function testOpenApiFixturesProduceAdjacentDtoDefinitions(string $schemaFile, bool $includeComponentSchemas): void
    {
        $schema = json_decode((new Filesystem())->readFile($schemaFile), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($schema);
        $definitions = (new OpenApiDtoSchemaParser())->parse($schema, includeComponentSchemas: $includeComponentSchemas);
        static::assertNotSame([], $definitions);

        $definitionNames = array_map(static fn (OpenApiDtoDefinition $definition): string => $definition->name, $definitions);
        $fixtureNames = [];
        foreach (Finder::create()->files()->depth(0)->name('*.php')->sortByName()->in(\dirname($schemaFile)) as $fixtureFile) {
            $fixtureNames[] = $fixtureFile->getBasename('.php');
        }

        sort($definitionNames);
        sort($fixtureNames);
        static::assertSame($fixtureNames, $definitionNames);
        static::assertCount(\count($definitionNames), array_unique($definitionNames));

        foreach ($definitions as $definition) {
            static::assertNotSame('', $definition->name);
            foreach ($definition->properties as $property) {
                static::assertNotSame('', $property->name);
                static::assertNotSame('', $property->phpType);
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
