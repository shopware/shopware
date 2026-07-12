<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\DefaultBindingSpecificationSynthesizer;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDtoCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('framework')]
class TypeConsistentBindingSpecificationValidationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const ID = 'binding';

    #[TestDox('accepts a binding whose resolves/inputs are consistent with the declared element type')]
    public function testValidBindingProducesNoViolations(): void
    {
        $dto = new BindingSpecificationDto(
            type: 'Sw:Media:Image',
            label: 'Image binding',
            resolves: [
                'media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            ],
            inputs: [
                // "mediaId" is the reference's undeclared resolvedBy storage key, not a declared primitive
                // property; "loading" exercises the inputs-facet validation against a real declared primitive.
                'loading' => ['default' => 'lazy', 'required' => false],
            ],
        );

        $violations = $this->validator()->validate(new BindingSpecificationDtoCollection([self::ID => $dto]));

        static::assertCount(0, $violations);
    }

    /**
     * @param array<string, mixed> $resolves
     * @param array<string, mixed> $inputs
     */
    #[DataProvider('invalidBindingsProvider')]
    #[TestDox('rejects a binding whose resolves/inputs are inconsistent with the declared element type')]
    public function testInvalidBindingProducesViolationAtExpectedPath(mixed $type, array $resolves, array $inputs, string $expectedPath): void
    {
        $dto = new BindingSpecificationDto($type, 'invalid binding', $resolves, $inputs);

        $violations = $this->validator()->validate(new BindingSpecificationDtoCollection([self::ID => $dto]));

        static::assertGreaterThan(0, $violations->count(), 'Expected at least one violation.');

        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        // The semantic constraint runs on the collection, so violation paths are keyed on the binding id.
        static::assertContains('bindings[' . self::ID . '].' . $expectedPath, $paths);
    }

    /**
     * @return iterable<string, array{type: mixed, resolves: array<string, mixed>, inputs: array<string, mixed>, expectedPath: string}>
     */
    public static function invalidBindingsProvider(): iterable
    {
        yield 'unknown type' => [
            'type' => 'Sw:Does:NotExist',
            'resolves' => [],
            'inputs' => [],
            'expectedPath' => 'type',
        ];
        yield 'resolves key is not a reference property' => [
            'type' => 'Sw:Media:Image',
            'resolves' => [
                'mediaId' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']],
            ],
            'inputs' => [],
            'expectedPath' => 'resolves[mediaId]',
        ];
        yield 'loader produces a type not assignable to the declared property type' => [
            'type' => 'Sw:Media:Image',
            'resolves' => [
                'media' => ['loader' => 'entity', 'config' => ['entity' => 'product', 'property' => 'mediaId']],
            ],
            'inputs' => [],
            'expectedPath' => 'resolves[media]',
        ];
        yield 'undecodable loader config' => [
            'type' => 'Sw:Media:Image',
            'resolves' => [
                'media' => ['loader' => 'entity', 'config' => []],
            ],
            'inputs' => [],
            'expectedPath' => 'resolves[media].config',
        ];
        yield 'loader is not a registered data loader' => [
            'type' => 'Sw:Media:Image',
            'resolves' => [
                'media' => ['loader' => 'not-a-registered-loader', 'config' => []],
            ],
            'inputs' => [],
            'expectedPath' => 'resolves[media]',
        ];
        yield 'entity loader property names a non-primitive property' => [
            // config decodes and the produced MediaEntity is assignable, but the "property" that should
            // hold the id names "media" (itself a reference), not a primitive id property.
            'type' => 'Sw:Media:Image',
            'resolves' => [
                'media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'media']],
            ],
            'inputs' => [],
            'expectedPath' => 'resolves[media].config.property',
        ];
        yield 'inputs key is not a primitive property' => [
            'type' => 'Sw:Media:Image',
            'resolves' => [],
            'inputs' => [
                'media' => ['default' => 'not-a-primitive-property'],
            ],
            'expectedPath' => 'inputs[media]',
        ];
        yield 'input default type mismatch' => [
            'type' => 'Sw:Media:Image',
            'resolves' => [],
            'inputs' => [
                'maxImageWidth' => ['default' => 'not-an-integer'],
            ],
            'expectedPath' => 'inputs[maxImageWidth].default',
        ];
        yield 'context form is rejected' => [
            'type' => 'Sw:Media:Image',
            'resolves' => [
                'media' => ['context' => 'product.cover'],
            ],
            'inputs' => [],
            'expectedPath' => 'resolves[media]',
        ];
        yield 'entity loader config decodes but names an unregistered entity' => [
            // config decodes fine (both "entity" and "property" are non-empty strings), but
            // resolveProducedType() throws ContentSystemException::unknownLoaderEntity() for the
            // unregistered entity name -- this exercises the resolveProducedType() catch, distinct
            // from the "undecodable loader config" case above, which exercises the decodeConfig() catch.
            'type' => 'Sw:Media:Image',
            'resolves' => [
                'media' => ['loader' => 'entity', 'config' => ['entity' => 'this_entity_does_not_exist', 'property' => 'name']],
            ],
            'inputs' => [],
            'expectedPath' => 'resolves[media].config',
        ];
    }

    #[TestDox('surfaces the produced-type mismatch through the YAML load path as a bindingSpecificationsInvalid exception')]
    public function testLoadPathThrowsForProducedTypeMismatch(): void
    {
        $directory = sys_get_temp_dir() . '/' . uniqid('content-system-binding-spec-test-', true);
        $filesystem = new Filesystem();
        $filesystem->mkdir($directory . '/media');

        try {
            // The type is implicit: media/image.yaml under prefix Sw resolves to the registered Sw:Media:Image,
            // whose "media" reference is a MediaEntity, so the entity loader producing a ProductEntity is a
            // produced-type mismatch caught at load time.
            file_put_contents($directory . '/media/image.yaml', Yaml::dump([
                'bindings' => [
                    'image-binding' => [
                        'label' => 'Image binding',
                        'resolves' => [
                            'media' => ['loader' => 'entity', 'config' => ['entity' => 'product', 'property' => 'mediaId']],
                        ],
                    ],
                ],
            ], 6));

            $loader = new YamlBindingSpecificationLoader(
                [],
                new ElementTypeNameResolver(),
                new DefaultBindingSpecificationSynthesizer(),
                new BindingSpecificationSerializer(),
                $this->canonicalizer(),
                $this->validator(),
            );

            try {
                $loader->loadDtosFromTypeDirectory($directory, 'test', 'Sw');
                static::fail('Expected the loader to reject the produced-type mismatch.');
            } catch (ContentSystemException $exception) {
                static::assertSame(ContentSystemException::BINDING_SPECIFICATIONS_INVALID, $exception->getErrorCode());
                static::assertStringContainsString('resolves[media]', $exception->getMessage());
            }
        } finally {
            $filesystem->remove($directory);
        }
    }

    private function validator(): ValidatorInterface
    {
        $validator = $this->getContainer()->get('validator');
        static::assertInstanceOf(ValidatorInterface::class, $validator);

        return $validator;
    }

    private function canonicalizer(): BindingSpecificationCanonicalizer
    {
        $canonicalizer = $this->getContainer()->get(BindingSpecificationCanonicalizer::class);
        static::assertInstanceOf(BindingSpecificationCanonicalizer::class, $canonicalizer);

        return $canonicalizer;
    }
}
