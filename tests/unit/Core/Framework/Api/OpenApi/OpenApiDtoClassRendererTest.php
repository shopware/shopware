<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoClassRenderer;
use Shopware\Core\Framework\Api\OpenApi\OpenApiDtoDefinition;
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
        $class = $this->loadRenderedClass('constants/schema.json', 'ConstValues');
        $dto = $class->newInstance('fixed');
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        static::assertSame(['kind' => 'fixed'], get_object_vars($dto));
        static::assertCount(0, $validator->validate($dto));

        foreach (['optionalKind' => 'fixed', 'enabled' => false, 'count' => 0, 'ratio' => 1.0, 'quoted' => 'it\'s\\fixed'] as $name => $value) {
            $class->getProperty($name)->setValue($dto, $value);
        }
        static::assertCount(0, $validator->validate($dto));

        foreach (['kind' => 'wrong', 'optionalKind' => 'wrong', 'enabled' => true, 'count' => 1, 'ratio' => 2.0, 'quoted' => 'wrong'] as $name => $value) {
            $class->getProperty($name)->setValue($dto, $value);
        }
        $violations = $validator->validate($dto);
        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }
        static::assertEqualsCanonicalizing(['kind', 'optionalKind', 'enabled', 'count', 'ratio', 'quoted'], $paths);
    }

    public function testOnlyRequiredPropertiesArePromotedConstructorParameters(): void
    {
        $class = $this->loadRenderedClass('presence/schema.json', 'Presence');

        foreach ($class->getProperties() as $property) {
            static::assertSame(
                \in_array($property->getName(), ['requiredValue', 'requiredNullable'], true),
                $property->isPromoted(),
                $property->getName(),
            );
        }

        $constructor = $class->getConstructor();
        static::assertNotNull($constructor);
        static::assertSame(
            ['requiredValue', 'requiredNullable'],
            array_map(static fn (\ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()),
        );
        static::assertSame(2, $constructor->getNumberOfRequiredParameters());
        $dto = $class->newInstance('value', null);
        static::assertSame(['requiredValue' => 'value', 'requiredNullable' => null], get_object_vars($dto));
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

        $dto = $serializer->denormalize($data, $class->getName());
        static::assertInstanceOf($class->getName(), $dto);
        static::assertSame(2, $class->getProperty('totalCountMode')->getValue($dto));
        static::assertSame('filter', $class->getProperty('postFilter')->getValue($dto));
        static::assertJsonStringEqualsJsonString(json_encode($data, \JSON_THROW_ON_ERROR), $serializer->serialize($dto, 'json'));
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

    public function testOpenApiFixturesMatchGeneratedDtos(): void
    {
        $parser = new OpenApiDtoSchemaParser();
        $renderer = new OpenApiDtoClassRenderer(new MockClock('2026-07-07 00:00:00'));
        $filesystem = new Filesystem();
        $fixtureDirectories = Finder::create()
            ->directories()
            ->depth(0)
            ->exclude('invalidSchemas')
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
     * @return \ReflectionClass<object>
     */
    private function loadRenderedClass(string $schema, string $name): \ReflectionClass
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

            return new \ReflectionClass($class);
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
