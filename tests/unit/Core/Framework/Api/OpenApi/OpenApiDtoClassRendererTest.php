<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OpenApi;

use App\DTO\ConstValues;
use App\DTO\Presence;
use App\DTO\WireNames;
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
     * @return array<string, mixed>
     */
    private function loadSchema(string $fixture): array
    {
        $schema = json_decode((new Filesystem())->readFile(__DIR__ . '/_fixtures/' . $fixture), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($schema);

        return $schema;
    }
}
