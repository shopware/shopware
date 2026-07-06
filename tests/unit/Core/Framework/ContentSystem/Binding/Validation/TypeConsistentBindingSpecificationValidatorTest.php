<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDtoCollection;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\TypeConsistentBindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\TypeConsistentBindingSpecificationValidator;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(TypeConsistentBindingSpecificationValidator::class)]
class TypeConsistentBindingSpecificationValidatorTest extends TestCase
{
    private const ID = 'from-media-library';

    #[TestDox('rethrows a non-client-defect ContentSystemException raised while decoding a resolves config')]
    public function testRethrowsNonClientDefectExceptionFromDecode(): void
    {
        $type = new ContentSystemElementTypeSpecification(
            'Sw:Media:Image',
            'Image',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            ['media' => new PropertySpecification(
                'media',
                new PropertyType(Entity::class, false, null, null),
                false,
                '',
                '',
                null,
            )],
            [],
        );

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('get')->willReturn($type);

        // INVALID_FIELD_TYPE is NOT in CLIENT_DEFECT_CODES, so decodeConfig() must rethrow it rather than
        // turning it into a violation.
        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willThrowException(ContentSystemException::invalidFieldType('A', 'B'));

        $validator = new TypeConsistentBindingSpecificationValidator(
            $registry,
            $provider,
            static::createStub(RootContextMapper::class),
            static::createStub(AbstractContentSystemDataLoaderMapResolver::class),
        );
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $dto = new BindingSpecificationDto(
            type: 'Sw:Media:Image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => []]],
            inputs: [],
        );

        try {
            $validator->validate(new BindingSpecificationDtoCollection([self::ID => $dto]), new TypeConsistentBindingSpecification());
            static::fail('Expected a ContentSystemException to be rethrown.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::INVALID_FIELD_TYPE, $e->getErrorCode());
        }
    }

    #[TestDox('a resolves entry propertyReference config key naming a primitive property passes')]
    public function testPropertyReferenceKeyNamingPrimitivePasses(): void
    {
        // The loader identifier is never branched on by the validator (validatePropertyReferenceKeys() only uses
        // it as a ContentSystemDataLoaderMap lookup key), so a second loader here would exercise the same path.
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']]],
            inputs: [],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

    #[TestDox('a resolves entry propertyReference config key naming $_dataName is a violation')]
    #[DataProvider('nonPrimitivePropertyProvider')]
    public function testPropertyReferenceKeyNamingNonPrimitiveIsViolation(string $propertyValue): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => $propertyValue]]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config.property', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('primitive property', (string) $violations->get(0)->getMessage());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonPrimitivePropertyProvider(): iterable
    {
        // The loader identifier ('entity' vs. any other registered loader) is not varied here: it is never
        // branched on by validatePropertyReferenceKeys(), only used as a ContentSystemDataLoaderMap lookup key
        // (configSpecificationFor()), so both loaders would traverse identical SUT branches.
        yield 'a reference property' => ['media'];
        yield 'a missing property' => ['ghost'];
    }

    #[TestDox('resolves the declared type from the overlay when the registry does not carry it, and validates against it')]
    public function testResolvesTypeFromOverlayWhenRegistryLacksIt(): void
    {
        // The registry has no type at all (an app's own type at install time); the overlay supplies it, so the
        // propertyReference check still runs against the overlay spec and rejects a non-primitive value.
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);

        $validator = $this->validatorWithRegistry($registry, $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'media']]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator, ['image' => $this->imageType()]);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config.property', $violations->get(0)->getPropertyPath());
    }

    #[TestDox('prefers the overlay type over a registered type of the same name')]
    public function testOverlayTakesPrecedenceOverRegistry(): void
    {
        // The registry carries an "image" WITHOUT any properties; only the overlay's "image" declares them. The
        // dto validates cleanly, proving resolveType() picked the overlay: registry-first would violate on every
        // key. Mirrors the canonicalizer twin (its resolveType can diverge independently).
        $bareType = new ContentSystemElementTypeSpecification('image', 'Image', '', null, null, new CopilotSpecification('', []), [], []);
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('get')->willReturn($bareType);

        $validator = $this->validatorWithRegistry($registry, $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']]],
            inputs: [],
        );

        static::assertCount(0, $this->validateWith($dto, $validator, ['image' => $this->imageType()]));
    }

    #[TestDox('reports an unknown-type violation keyed on the binding when the type is in neither the overlay nor the registry')]
    public function testUnknownTypeViolationWhenAbsentFromOverlayAndRegistry(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);

        $validator = $this->validatorWithRegistry($registry, $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].type', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('not a registered element type', (string) $violations->get(0)->getMessage());
    }

    private function validator(ContentSystemElementTypeSpecification $type, ContentSystemDataLoaderMap $map): TypeConsistentBindingSpecificationValidator
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('get')->willReturn($type);

        return $this->validatorWithRegistry($registry, $map);
    }

    private function validatorWithRegistry(AbstractContentSystemElementTypeRegistry $registry, ContentSystemDataLoaderMap $map): TypeConsistentBindingSpecificationValidator
    {
        // decode + resolveType succeed with an assignable produced type, so the flow reaches the generalized
        // propertyReference check that is under test here.
        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

        $rootContextMapper = static::createStub(RootContextMapper::class);
        $rootContextMapper->method('resolveType')->willReturn(MediaEntity::class);

        $mapResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $mapResolver->method('resolve')->willReturn($map);

        return new TypeConsistentBindingSpecificationValidator($registry, $provider, $rootContextMapper, $mapResolver);
    }

    /**
     * @param array<string, LoaderConfigSpecification> $specifications
     */
    private function map(array $specifications): ContentSystemDataLoaderMap
    {
        return new ContentSystemDataLoaderMap([], $specifications);
    }

    private function loaderSpec(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
        ]);
    }

    private function imageType(): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            'image',
            'Image',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [
                'media' => new PropertySpecification('media', new PropertyType(MediaEntity::class, false, null, null), false, '', '', null),
                'mediaId' => new PropertySpecification('mediaId', new PropertyType('string', false, null, null), false, '', '', null),
            ],
            [],
        );
    }

    /**
     * @param array<string, ContentSystemElementTypeSpecification> $typeOverlay
     */
    private function validateWith(BindingSpecificationDto $dto, TypeConsistentBindingSpecificationValidator $validator, array $typeOverlay = []): ConstraintViolationListInterface
    {
        $factory = new class($validator) implements ConstraintValidatorFactoryInterface {
            public function __construct(private readonly TypeConsistentBindingSpecificationValidator $validator)
            {
            }

            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                return $this->validator;
            }
        };

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory($factory)
            ->getValidator()
            ->validate(new BindingSpecificationDtoCollection([self::ID => $dto], $typeOverlay), new TypeConsistentBindingSpecification());
    }
}
