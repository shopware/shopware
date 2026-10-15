<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoDefinition;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoSchemaParser;
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
    public function testReferencedInputsBecomeRequiredConstructorArguments(): void
    {
        $class = $this->loadRenderedClass('referencedInputs/schema.json', 'CreateInputRequest');
        $serializer = new Serializer([new DtoNormalizer(new PropertyNormalizer())]);
        $data = ['bodyValue' => 'body', 'itemId' => 'item', 'limit' => 10, 'version' => 2];
        $dto = $serializer->denormalize($data, $class);
        static::assertEquals($data, get_object_vars($dto));
    }

    public function testExtensionsRemainOptionalPluginData(): void
    {
        $class = $this->loadRenderedClass('extensionsProperty/schema.json', 'RequiredId');
        $serializer = new Serializer([new DtoNormalizer(new PropertyNormalizer())], [new JsonEncoder()]);

        foreach ([['id' => 'input'], ['id' => 'input', 'extensions' => []], ['id' => 'input', 'extensions' => ['custom' => ['value' => 'input']]], ['id' => 'input', 'extensions' => null]] as $data) {
            $dto = $serializer->denormalize($data, $class);
            static::assertEquals($data, get_object_vars($dto));
            static::assertJsonStringEqualsJsonString(json_encode($data, \JSON_THROW_ON_ERROR), $serializer->serialize($dto, 'json'));
        }
    }

    public function testRequestDefaultsPreserveOmissionAndExplicitValues(): void
    {
        $class = $this->loadRenderedClass('requestDefaults/schema.json', 'CreateDefaultsRequest');
        $serializer = new Serializer([new DtoNormalizer(new PropertyNormalizer())], [new JsonEncoder()]);
        $dto = $serializer->denormalize(['id' => 'input'], $class);

        static::assertSame(['id' => 'input'], get_object_vars($dto));
        static::assertJsonStringEqualsJsonString('{"id":"input"}', $serializer->serialize($dto, 'json'));

        $data = ['id' => 'input', 'count' => 0, 'active' => false, 'comment' => null, 'source' => 'api'];
        $dto = $serializer->denormalize($data, $class);
        static::assertEquals($data, get_object_vars($dto));
        static::assertJsonStringEqualsJsonString(json_encode($data, \JSON_THROW_ON_ERROR), $serializer->serialize($dto, 'json'));
    }

    public function testGeneratedConstantsValidateWithoutDefaults(): void
    {
        $class = $this->loadRenderedClass('constants/schema.json', 'ConstValues');
        $dto = new $class('fixed');
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        static::assertSame(['kind' => 'fixed'], get_object_vars($dto));
        static::assertCount(0, $validator->validate($dto));

        $serializer = new Serializer([new DtoNormalizer(new PropertyNormalizer())]);
        $dto = $serializer->denormalize(['kind' => 'fixed', 'optionalKind' => 'fixed', 'enabled' => false, 'count' => 0, 'ratio' => 1.0, 'quoted' => 'it\'s\\fixed'], $class);
        static::assertCount(0, $validator->validate($dto));

        $dto = $serializer->denormalize(['kind' => 'wrong', 'optionalKind' => 'wrong', 'enabled' => true, 'count' => 1, 'ratio' => 2.0, 'quoted' => 'wrong'], $class);
        $violations = $validator->validate($dto);
        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }
        static::assertEqualsCanonicalizing(['kind', 'optionalKind', 'enabled', 'count', 'ratio', 'quoted'], $paths);
    }

    public function testGeneratedWireNamesWorkWithSerializer(): void
    {
        $class = $this->loadRenderedClass('wire-names/schema.json', 'WireNames');
        $metadata = new ClassMetadataFactory(new AttributeLoader());
        $serializer = new Serializer([new DtoNormalizer(new PropertyNormalizer($metadata, new MetadataAwareNameConverter($metadata)))], [new JsonEncoder()]);
        $data = [
            'total-count-mode' => 2,
            'post-filter' => 'filter',
            'camelCase' => 'unchanged',
            'snake_case' => 'snake',
            'quote\'field' => 'quoted',
        ];

        $dto = $serializer->denormalize($data, $class);
        static::assertInstanceOf($class, $dto);
        static::assertSame(2, get_object_vars($dto)['totalCountMode']);
        static::assertSame('filter', get_object_vars($dto)['postFilter']);
        static::assertJsonStringEqualsJsonString(json_encode($data, \JSON_THROW_ON_ERROR), $serializer->serialize($dto, 'json'));
    }

    public function testEnumCaseNameCollisionsThrowException(): void
    {
        $this->expectException(FrameworkException::class);

        $definitions = (new OpenApiDtoSchemaParser())->parse($this->loadSchema('invalidSchemas/enumCaseCollision.json'));

        $this->renderDefinition($this->definitionByName($definitions, 'CollidingValue'));
    }

    public function testEnumValuesWithoutCaseNameThrowException(): void
    {
        $this->expectException(FrameworkException::class);

        $definitions = (new OpenApiDtoSchemaParser())->parse($this->loadSchema('invalidSchemas/emptyEnumCase.json'));

        $this->renderDefinition($this->definitionByName($definitions, 'InvalidValue'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function openApiFixtures(): iterable
    {
        foreach (Finder::create()->files()->name('*.json')->notPath('invalidSchemas')->sortByName()->in(__DIR__ . '/_fixtures') as $schemaFile) {
            yield $schemaFile->getRelativePathname() => [$schemaFile->getPathname(), false];
            if (\in_array($schemaFile->getRelativePathname(), ['requestDefaults/schema.json', 'responseComponent/schema.json'], true)) {
                yield $schemaFile->getRelativePathname() . ' (component entry point)' => [$schemaFile->getPathname(), true];
            }
        }
    }

    #[DataProvider('openApiFixtures')]
    public function testOpenApiFixturesMatchGeneratedDtos(string $schemaFile, bool $componentsOnly): void
    {
        $filesystem = new Filesystem();
        $schema = json_decode($filesystem->readFile($schemaFile), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($schema);
        $namespaces = [];
        $externalNamespaces = [];
        foreach (Finder::create()->files()->depth(0)->name('*.php')->in(\dirname($schemaFile)) as $fixtureFile) {
            static::assertSame(1, preg_match('/^namespace ([^;]+);/m', $filesystem->readFile($fixtureFile->getPathname()), $matches));
            $namespace = $matches[1];
            $namespaces[$fixtureFile->getBasename('.php')] = $namespace;
            $externalNamespaces[$namespace . '\\' . $fixtureFile->getBasename('.php')] = $namespace;
        }

        $renderer = new OpenApiDtoClassRenderer(new MockClock('2026-07-07 00:00:00'));
        $parser = new OpenApiDtoSchemaParser();
        $schemaNames = [];
        if ($componentsOnly) {
            foreach (array_keys($schema['components']['schemas']) as $schemaName) {
                static::assertIsString($schemaName);
                $schemaNames[] = $schemaName;
            }
        }
        $definitions = $componentsOnly
            ? $parser->parseComponents($schema, $schemaNames, 'App\\DTO')
            : $parser->parse($schema);
        static::assertNotSame([], $definitions);
        foreach ($definitions as $definition) {
            $name = substr($definition->name, (int) strrpos('\\' . $definition->name, '\\'));
            $expected = $filesystem->readFile(\dirname($schemaFile) . '/' . $name . '.php');
            $generated = $renderer->renderClass($definition, $namespaces[$name], $externalNamespaces);
            static::assertSame(
                preg_replace('/Last generated: [^\\r\\n]+/', 'Last generated: <generated>', $expected),
                preg_replace('/Last generated: [^\\r\\n]+/', 'Last generated: <generated>', $generated),
                $definition->name,
            );
        }
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

    /**
     * @return class-string
     */
    private function loadRenderedClass(string $schema, string $name): string
    {
        $definitions = (new OpenApiDtoSchemaParser())->parse($this->loadSchema($schema));
        $namespace = __NAMESPACE__ . '\\Generated' . bin2hex(random_bytes(8));
        $source = (new OpenApiDtoClassRenderer(new MockClock('2026-07-14')))->renderClass(
            $this->definitionByName($definitions, $name),
            $namespace,
        );
        $filesystem = new Filesystem();
        $file = $filesystem->tempnam(sys_get_temp_dir(), 'open-api-dto-');

        try {
            $filesystem->dumpFile($file, $source);
            require $file;

            $class = $namespace . '\\' . $name;
            static::assertTrue(class_exists($class, false));

            return $class;
        } finally {
            $filesystem->remove($file);
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
