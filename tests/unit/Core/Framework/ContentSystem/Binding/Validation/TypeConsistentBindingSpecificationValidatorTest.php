<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\RootSourceConfigMap;
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
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TypeConsistentBindingSpecificationValidator::class)]
class TypeConsistentBindingSpecificationValidatorTest extends TestCase
{
    private const ID = 'media-picker';

    #[DataProvider('passesPropertyReferenceKeyNamingProvider')]
    #[TestDox('passes a resolves entry whose propertyReference config key names $_dataName')]
    public function testPropertyReferenceKeyNamingPasses(string $propertyValue): void
    {
        // The loader identifier is never branched on by the validator (validatePropertyReferenceKeys() only uses
        // it as a ContentSystemDataLoaderMap lookup key), so a second loader here would exercise the same path.
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => $propertyValue]]],
            inputs: [],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

    #[DataProvider('rejectsNonPrimitivePropertyProvider')]
    #[TestDox('flags a resolves entry propertyReference config key naming $_dataName as a violation')]
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

    #[DataProvider('rejectsPrimitiveOutsideStringReferencedTypeProvider')]
    #[TestDox('flags a string-referencing propertyReference config key naming $_dataName as a violation')]
    public function testStringPropertyReferenceKeyNamingNonStringPrimitiveIsViolation(string $propertyValue, string $declaredType): void
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
        static::assertSame(
            \sprintf('resolves config key "property" must name a property of type "image" that can hold a "string" value, but "%s" is declared "%s"', $propertyValue, $declaredType),
            (string) $violations->get(0)->getMessage(),
        );
    }

    #[TestDox('flags a list-referencing propertyReference config key naming a string property as a violation, since no primitive holds a list')]
    public function testListPropertyReferenceKeyNamingStringPropertyIsViolation(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->listLoaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'ids' => 'mediaId']]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config.ids', $violations->get(0)->getPropertyPath());
        static::assertSame(
            'resolves config key "ids" must name a property of type "image" that can hold a "list<string>" value, but "mediaId" is declared "string"',
            (string) $violations->get(0)->getMessage(),
        );
    }

    #[TestDox('passes a list-referencing propertyReference config key naming an undeclared key')]
    public function testListPropertyReferenceKeyNamingUndeclaredKeyPasses(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->listLoaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'ids' => 'mediaIds']]],
            inputs: [],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

    #[TestDox('flags a later propertyReference config key when an earlier key names a property that can hold its referenced type')]
    public function testLaterPropertyReferenceKeyIsCheckedAfterAcceptedKey(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->twoPropertyReferenceLoaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId', 'fallback' => 'width']]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config.fallback', $violations->get(0)->getPropertyPath());
        static::assertSame(
            'resolves config key "fallback" must name a property of type "image" that can hold a "string" value, but "width" is declared "integer"',
            (string) $violations->get(0)->getMessage(),
        );
    }

    #[TestDox('flags every propertyReference config key that names a non-primitive property or a property that cannot hold its referenced type')]
    public function testEveryViolatingPropertyReferenceKeyIsReported(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->twoPropertyReferenceLoaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'media', 'fallback' => 'width']]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(2, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config.property', $violations->get(0)->getPropertyPath());
        static::assertSame('bindings[' . self::ID . '].resolves[media].config.fallback', $violations->get(1)->getPropertyPath());
    }

    #[TestDox('resolves the declared type from the overlay when the registry does not carry it')]
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
        $validator = $this->validatorWithRegistry($this->registryServing($bareType), $this->map(['entity' => $this->loaderSpec()]));

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

    #[TestDox('flags a resolves entry in the unsupported "context" form as a violation')]
    public function testResolvesEntryContextFormIsViolation(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['context' => 'root']],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media]', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('"context" form', (string) $violations->get(0)->getMessage());
    }

    #[DataProvider('rejectsNonReferenceResolvesKeyProvider')]
    #[TestDox('flags a resolves entry whose key names $_dataName rather than a reference property as a violation')]
    public function testResolvesEntryKeyNotReferencePropertyIsViolation(string $key): void
    {
        $validator = $this->validator($this->referenceVariantsType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [$key => ['loader' => 'entity']],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[' . $key . ']', $violations->get(0)->getPropertyPath());
        static::assertSame(
            \sprintf('resolves entry "%s" does not name a reference property of type "image"', $key),
            (string) $violations->get(0)->getMessage(),
        );
    }

    #[TestDox('flags a resolves entry naming an unregistered loader as a violation')]
    public function testResolvesEntryLoaderNotRegisteredIsViolation(): void
    {
        $validator = $this->validatorFailingDecodeWith(ContentSystemException::configSerializerNotRegistered('ghost-loader'));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'ghost-loader', 'config' => []]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media]', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('not a registered data loader', (string) $violations->get(0)->getMessage());
    }

    #[TestDox('flags a resolves entry whose produced type is not assignable to the declared reference type as a violation')]
    public function testResolvesEntryProducedTypeNotAssignableIsViolation(): void
    {
        // The loader produces a bare Entity, which is not assignable to the declared MediaEntity reference of "media".
        $validator = $this->validatorProducing(Entity::class);

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media]', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('not assignable', (string) $violations->get(0)->getMessage());
    }

    #[TestDox('accepts a resolves entry whose produced type is a subclass of the declared reference type')]
    public function testResolvesEntryProducedSubclassOfDeclaredTypeIsAccepted(): void
    {
        // The loader produces a MediaEntity, which is a subclass of the declared bare Entity reference of "media".
        $validator = $this->validator($this->referenceVariantsType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']]],
            inputs: [],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

    #[TestDox('reports only the not-assignable violation when the config also names a non-primitive property')]
    public function testResolvesEntryNotAssignableSkipsPropertyReferenceCheck(): void
    {
        // The config's property "media" is a reference property, which the propertyReference check would flag as a
        // second violation if the not-assignable violation did not end the entry's validation.
        $validator = $this->validatorProducing(Entity::class);

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'media']]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media]', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('not assignable', (string) $violations->get(0)->getMessage());
    }

    #[DataProvider('rejectsNonPrimitiveInputsKeyProvider')]
    #[TestDox('flags an inputs entry whose key names $_dataName rather than a primitive property as a violation')]
    public function testInputsEntryKeyNotPrimitivePropertyIsViolation(string $key): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [],
            inputs: [$key => ['default' => 'seed']],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].inputs[' . $key . ']', $violations->get(0)->getPropertyPath());
        static::assertSame(
            \sprintf('inputs entry "%s" does not name a primitive property of type "image"', $key),
            (string) $violations->get(0)->getMessage(),
        );
    }

    #[TestDox('flags an inputs entry whose default value does not match the declared primitive type as a violation')]
    public function testInputsEntryDefaultTypeMismatchIsViolation(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [],
            inputs: ['mediaId' => ['default' => 42]],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].inputs[mediaId].default', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('must match the declared type', (string) $violations->get(0)->getMessage());
    }

    #[TestDox('accepts an integer inputs default on an integer property')]
    public function testInputsEntryIntegerDefaultOnIntegerPropertyIsAccepted(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [],
            inputs: ['width' => ['default' => 7]],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

    #[DataProvider('nonIntegerDefaultProvider')]
    #[TestDox('flags $_dataName as an inputs default on an integer property as a violation')]
    public function testInputsEntryNonIntegerDefaultOnIntegerPropertyIsViolation(string|float $default): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [],
            inputs: ['width' => ['default' => $default]],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].inputs[width].default', $violations->get(0)->getPropertyPath());
        static::assertSame('inputs entry "width" default value must match the declared type "integer"', (string) $violations->get(0)->getMessage());
    }

    #[TestDox('accepts a boolean inputs default on a boolean property')]
    public function testInputsEntryBooleanDefaultOnBooleanPropertyIsAccepted(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [],
            inputs: ['autoplay' => ['default' => true]],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

    #[TestDox('flags an integer inputs default on a boolean property as a violation')]
    public function testInputsEntryNonBooleanDefaultOnBooleanPropertyIsViolation(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [],
            inputs: ['autoplay' => ['default' => 1]],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].inputs[autoplay].default', $violations->get(0)->getPropertyPath());
        static::assertSame('inputs entry "autoplay" default value must match the declared type "boolean"', (string) $violations->get(0)->getMessage());
    }

    #[DataProvider('numericDefaultProvider')]
    #[TestDox('accepts $_dataName as an inputs default on a number property')]
    public function testInputsEntryNumericDefaultOnNumberPropertyIsAccepted(int|float $default): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [],
            inputs: ['ratio' => ['default' => $default]],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

    #[DataProvider('nonNumberDefaultProvider')]
    #[TestDox('flags $_dataName as an inputs default on a number property as a violation')]
    public function testInputsEntryNonNumericDefaultOnNumberPropertyIsViolation(string $default): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [],
            inputs: ['ratio' => ['default' => $default]],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].inputs[ratio].default', $violations->get(0)->getPropertyPath());
        static::assertSame('inputs entry "ratio" default value must match the declared type "number"', (string) $violations->get(0)->getMessage());
    }

    #[TestDox('accepts an inputs entry whose scalar default targets a translatable property')]
    public function testInputsEntryScalarDefaultOnTranslatableTargetIsAccepted(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [],
            inputs: ['caption' => ['default' => 'Autumn sale']],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

    #[TestDox('flags a null inputs default on a translatable target as a violation')]
    public function testInputsEntryNullDefaultOnTranslatableTargetIsViolation(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [],
            inputs: ['caption' => ['default' => null]],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].inputs[caption].default', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('not a valid language-map entry', (string) $violations->get(0)->getMessage());
    }

    #[TestDox('accepts a null inputs default on a non-translatable target')]
    public function testInputsEntryNullDefaultOnNonTranslatableTargetIsAccepted(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: [],
            inputs: ['mediaId' => ['default' => null]],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

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

        $registry = $this->registryServing($type);

        // INVALID_FIELD_TYPE is NOT in CLIENT_DEFECT_CODES, so decodeConfig() must rethrow it rather than
        // turning it into a violation.
        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willThrowException(ContentSystemException::invalidFieldType('A', 'B'));

        $validator = new TypeConsistentBindingSpecificationValidator(
            $registry,
            $provider,
            static::createStub(RootContextMapper::class),
            static::createStub(AbstractContentSystemDataLoaderMapResolver::class),
            static::createStub(RootSourceRegistry::class),
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

    #[TestDox('rethrows a non-client-defect ContentSystemException raised while resolving a produced type')]
    public function testRethrowsNonClientDefectExceptionFromProducedTypeResolution(): void
    {
        // Sibling of the decode rethrow above: decode succeeds, then resolveProducedType() raises a non-client
        // defect (INVALID_FIELD_TYPE is NOT in CLIENT_DEFECT_CODES), which must escape rather than become a
        // config violation.
        $validator = $this->validatorFailingProducedTypeWith(ContentSystemException::invalidFieldType('A', 'B'));
        $validator->initialize(static::createStub(ExecutionContextInterface::class));

        $dto = new BindingSpecificationDto(
            type: 'image',
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

    #[TestDox('throws an UnexpectedTypeException when handed a constraint other than TypeConsistentBindingSpecification')]
    public function testThrowsOnForeignConstraint(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));
        $constraint = new NotBlank();

        $this->expectExceptionObject(new UnexpectedTypeException($constraint, TypeConsistentBindingSpecification::class));

        $validator->validate(new BindingSpecificationDtoCollection([]), $constraint);
    }

    #[TestDox('throws an UnexpectedTypeException when handed a value that is not a BindingSpecificationDtoCollection')]
    public function testThrowsOnNonCollectionValue(): void
    {
        $validator = $this->validator($this->imageType(), $this->map(['entity' => $this->loaderSpec()]));

        $this->expectExceptionObject(new UnexpectedTypeException('not-a-collection', BindingSpecificationDtoCollection::class));

        $validator->validate('not-a-collection', new TypeConsistentBindingSpecification());
    }

    #[TestDox('flags a resolves entry whose config fails to decode as a config violation')]
    public function testResolvesEntryConfigDecodeFailureIsViolation(): void
    {
        $validator = $this->validatorFailingDecodeWith(ContentSystemException::invalidFieldValueType('property', 'string', 'integer'));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => []]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('config is invalid', (string) $violations->get(0)->getMessage());
    }

    #[TestDox('flags a resolves entry whose produced type fails to resolve as a config violation')]
    public function testResolvesEntryProducedTypeResolutionFailureIsViolation(): void
    {
        $validator = $this->validatorFailingProducedTypeWith(ContentSystemException::unknownLoaderEntity('ghost-entity'));

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => []]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('config is invalid', (string) $violations->get(0)->getMessage());
    }

    #[TestDox('passes a resolves entry whose config value is a scoped map with root-source keys and string values')]
    public function testResolvesEntryScopedMapPasses(): void
    {
        $validator = $this->validatorWithRootSources(['product', 'category', 'landing_page']);

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId', 'variant' => [RootSourceConfigMap::MARKER => ['product' => 'wide', 'category' => 'narrow']]]]],
            inputs: [],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

    #[TestDox('passes a resolves entry whose scoped maps name the same root sources in a different order')]
    public function testResolvesEntryScopedMapsNamingTheSameRootSourcesPass(): void
    {
        $validator = $this->validatorWithRootSources(['product', 'category', 'landing_page']);

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => [
                'entity' => 'media',
                'property' => 'mediaId',
                'variant' => [RootSourceConfigMap::MARKER => ['product' => 'wide', 'category' => 'narrow']],
                'format' => [RootSourceConfigMap::MARKER => ['category' => 'png', 'product' => 'jpg']],
            ]]],
            inputs: [],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

    /**
     * @param array<array-key, mixed> $value
     */
    #[DataProvider('nestedScopedMapProvider')]
    #[TestDox('flags a scoped map nested $_dataName as a violation at the config key that contains it')]
    public function testResolvesEntryNestedScopedMapIsViolation(array $value): void
    {
        $validator = $this->validatorWithRootSources(['product', 'category', 'landing_page']);

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId', 'variant' => $value]]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config.variant', $violations->get(0)->getPropertyPath());
        static::assertSame(
            'resolves entry "media" config key "variant" nests a scoped map inside its value, but a scoped map may only be the direct value of a config key',
            (string) $violations->get(0)->getMessage()
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function nestedScopedMapProvider(): iterable
    {
        yield 'in a map' => [['size' => [RootSourceConfigMap::MARKER => ['product' => 'wide']]]];
        yield 'in a list' => [[[RootSourceConfigMap::MARKER => ['product' => 'wide']]]];
        yield 'two levels down' => [['size' => ['inner' => [RootSourceConfigMap::MARKER => ['product' => 'wide']]]]];
    }

    #[TestDox('flags scoped maps in one config that name different root-source sets as a violation at the config')]
    public function testResolvesEntryScopedMapsNamingDifferentRootSourcesIsViolation(): void
    {
        $validator = $this->validatorWithRootSources(['product', 'category', 'landing_page']);

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => [
                'entity' => 'media',
                'property' => 'mediaId',
                'variant' => [RootSourceConfigMap::MARKER => ['product' => 'wide', 'category' => 'narrow']],
                'format' => [RootSourceConfigMap::MARKER => ['product' => 'jpg']],
            ]]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config', $violations->get(0)->getPropertyPath());
        static::assertSame(
            'resolves entry "media" config scopes its keys over different root-source sets (variant: category, product; format: product), but every scoped key of one config must name the same root sources',
            (string) $violations->get(0)->getMessage()
        );
    }

    #[TestDox('flags a scoped map whose key is not a registered root source as a violation')]
    public function testResolvesEntryScopedMapUnknownKeyIsViolation(): void
    {
        $validator = $this->validatorWithRootSources(['product', 'category', 'landing_page']);

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId', 'variant' => [RootSourceConfigMap::MARKER => ['product' => 'wide', 'bogus' => 'narrow']]]]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config.variant', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('must be a map of root source to string', (string) $violations->get(0)->getMessage());
    }

    #[TestDox('flags a scoped map with a non-string value as a violation')]
    public function testResolvesEntryScopedMapNonStringValueIsViolation(): void
    {
        $validator = $this->validatorWithRootSources(['product', 'category', 'landing_page']);

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId', 'variant' => [RootSourceConfigMap::MARKER => ['product' => ['wide']]]]]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config.variant', $violations->get(0)->getPropertyPath());
        static::assertStringContainsString('must be a map of root source to string', (string) $violations->get(0)->getMessage());
    }

    #[DataProvider('rejectsScopedMapOnNonLiteralKeyProvider')]
    #[TestDox('flags a scoped map on $_dataName as a violation')]
    public function testResolvesEntryScopedMapOnNonLiteralKeyIsViolation(string $configKey): void
    {
        $validator = $this->validatorWithRootSources(['product', 'category', 'landing_page']);

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', $configKey => [RootSourceConfigMap::MARKER => ['product' => 'mediaId', 'category' => 'mediaId']]]]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media].config.' . $configKey, $violations->get(0)->getPropertyPath());
        static::assertSame(
            'resolves entry "media" config key "' . $configKey . '" is a map of root source to value, which only a literal config key of loader "entity" may be',
            (string) $violations->get(0)->getMessage()
        );
    }

    #[TestDox('flags a scoped map on a key of an unregistered loader as the unregistered-loader violation')]
    public function testResolvesEntryScopedMapOnUnregisteredLoaderIsLoaderNotRegisteredViolation(): void
    {
        $validator = $this->validatorFailingDecodeWith(ContentSystemException::configSerializerNotRegistered('ghost-loader'), ['product']);

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'ghost-loader', 'config' => ['variant' => [RootSourceConfigMap::MARKER => ['product' => 'wide']]]]],
            inputs: [],
        );

        $violations = $this->validateWith($dto, $validator);

        static::assertCount(1, $violations);
        static::assertSame('bindings[' . self::ID . '].resolves[media]', $violations->get(0)->getPropertyPath());
        static::assertSame(
            'resolves entry "media" names loader "ghost-loader", which is not a registered data loader',
            (string) $violations->get(0)->getMessage()
        );
    }

    #[TestDox('treats an unscoped array config as an ordinary loader argument, not a scoped map')]
    public function testResolvesEntryUnscopedArrayConfigIsNotTreatedAsScopedMap(): void
    {
        $validator = $this->validatorWithRootSources(['product', 'category', 'landing_page']);

        $dto = new BindingSpecificationDto(
            type: 'image',
            label: 'label',
            resolves: ['media' => ['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId', 'associations' => ['manufacturer', 'cover']]]],
            inputs: [],
        );

        static::assertCount(0, $this->validateWith($dto, $validator));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectsScopedMapOnNonLiteralKeyProvider(): iterable
    {
        yield 'a propertyReference key' => ['property'];
        yield 'an entityName key' => ['entity'];
        yield 'a key the loader does not declare' => ['ghost'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function passesPropertyReferenceKeyNamingProvider(): iterable
    {
        yield 'a string property' => ['mediaId'];
        yield 'a translatable string property' => ['caption'];
        yield 'an undeclared key' => ['ghost'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectsPrimitiveOutsideStringReferencedTypeProvider(): iterable
    {
        yield 'an integer property' => ['width', 'integer'];
        yield 'a boolean property' => ['autoplay', 'boolean'];
        yield 'a number property' => ['ratio', 'number'];
        yield 'a translatable integer property' => ['position', 'integer (translatable)'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectsNonPrimitivePropertyProvider(): iterable
    {
        // The loader identifier ('entity' vs. any other registered loader) is not varied here: it is never
        // branched on by validatePropertyReferenceKeys(), only used as a ContentSystemDataLoaderMap lookup key
        // (configSpecificationFor()), so both loaders would traverse identical SUT branches.
        yield 'a reference property' => ['media'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectsNonReferenceResolvesKeyProvider(): iterable
    {
        yield 'a primitive property' => ['mediaId'];
        yield 'an undeclared key' => ['ghost'];
        yield 'an object property' => ['payload'];
        yield 'a union property' => ['identifier'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectsNonPrimitiveInputsKeyProvider(): iterable
    {
        yield 'a reference property' => ['media'];
        yield 'an undeclared key' => ['ghost'];
    }

    /**
     * @return iterable<string, array{string|float}>
     */
    public static function nonIntegerDefaultProvider(): iterable
    {
        yield 'a string' => ['7'];
        yield 'a float' => [1.5];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonNumberDefaultProvider(): iterable
    {
        yield 'a non-numeric string' => ['x'];
        yield 'a numeric string' => ['1.5'];
    }

    /**
     * @return iterable<string, array{int|float}>
     */
    public static function numericDefaultProvider(): iterable
    {
        yield 'a float' => [1.5];
        yield 'an integer' => [2];
    }

    private function validator(ContentSystemElementTypeSpecification $type, ContentSystemDataLoaderMap $map): TypeConsistentBindingSpecificationValidator
    {
        return $this->validatorWithRegistry($this->registryServing($type), $map);
    }

    private function registryServing(ContentSystemElementTypeSpecification $type): AbstractContentSystemElementTypeRegistry
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static function (string $name) use ($type): bool {
            static::assertSame($type->name(), $name);

            return true;
        });
        $registry->method('get')->willReturnCallback(static function (string $name) use ($type): ContentSystemElementTypeSpecification {
            static::assertSame($type->name(), $name);

            return $type;
        });

        return $registry;
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

        return new TypeConsistentBindingSpecificationValidator($registry, $provider, $rootContextMapper, $mapResolver, static::createStub(RootSourceRegistry::class));
    }

    /**
     * @param list<string> $rootSources
     */
    private function validatorWithRootSources(array $rootSources): TypeConsistentBindingSpecificationValidator
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('get')->willReturn($this->imageType());

        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

        $rootContextMapper = static::createStub(RootContextMapper::class);
        $rootContextMapper->method('resolveType')->willReturn(MediaEntity::class);

        $mapResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $mapResolver->method('resolve')->willReturn($this->map(['entity' => $this->loaderSpec()]));

        $rootSourceRegistry = static::createStub(RootSourceRegistry::class);
        $rootSourceRegistry->method('entityRootSources')->willReturn($rootSources);

        return new TypeConsistentBindingSpecificationValidator($registry, $provider, $rootContextMapper, $mapResolver, $rootSourceRegistry);
    }

    /**
     * Registry carries the image type, decode succeeds, but the loader's produced type is $producedType, so the
     * assignability check (is_a against the declared MediaEntity reference) drives the outcome.
     */
    private function validatorProducing(string $producedType): TypeConsistentBindingSpecificationValidator
    {
        $registry = $this->registryServing($this->imageType());

        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

        $rootContextMapper = static::createStub(RootContextMapper::class);
        $rootContextMapper->method('resolveType')->willReturn($producedType);

        $mapResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $mapResolver->method('resolve')->willReturn($this->map(['entity' => $this->loaderSpec()]));

        return new TypeConsistentBindingSpecificationValidator($registry, $provider, $rootContextMapper, $mapResolver, static::createStub(RootSourceRegistry::class));
    }

    /**
     * @param list<string> $rootSources
     */
    private function validatorFailingDecodeWith(ContentSystemException $exception, array $rootSources = []): TypeConsistentBindingSpecificationValidator
    {
        $registry = $this->registryServing($this->imageType());

        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willThrowException($exception);

        $mapResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $mapResolver->method('resolve')->willReturn($this->map(['entity' => $this->loaderSpec()]));

        $rootSourceRegistry = static::createStub(RootSourceRegistry::class);
        $rootSourceRegistry->method('entityRootSources')->willReturn($rootSources);

        return new TypeConsistentBindingSpecificationValidator(
            $registry,
            $provider,
            static::createStub(RootContextMapper::class),
            $mapResolver,
            $rootSourceRegistry,
        );
    }

    private function validatorFailingProducedTypeWith(ContentSystemException $exception): TypeConsistentBindingSpecificationValidator
    {
        $registry = $this->registryServing($this->imageType());

        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

        $rootContextMapper = static::createStub(RootContextMapper::class);
        $rootContextMapper->method('resolveType')->willThrowException($exception);

        $mapResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $mapResolver->method('resolve')->willReturn($this->map(['entity' => $this->loaderSpec()]));

        return new TypeConsistentBindingSpecificationValidator($registry, $provider, $rootContextMapper, $mapResolver, static::createStub(RootSourceRegistry::class));
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
            new ConfigKeySpecification('variant', ConfigKeyKind::Literal, 'string', required: false),
            new ConfigKeySpecification('format', ConfigKeyKind::Literal, 'string', required: false),
        ]);
    }

    private function listLoaderSpec(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('ids', ConfigKeyKind::PropertyReference, 'string', required: true, referencedType: 'list<string>'),
        ]);
    }

    private function twoPropertyReferenceLoaderSpec(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            new ConfigKeySpecification('fallback', ConfigKeyKind::PropertyReference, 'string', required: true),
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
                'caption' => new PropertySpecification('caption', new PropertyType('string', true, null, null), false, '', '', null),
                'width' => new PropertySpecification('width', new PropertyType('integer', false, null, null), false, '', '', null),
                'autoplay' => new PropertySpecification('autoplay', new PropertyType('boolean', false, null, null), false, '', '', null),
                'ratio' => new PropertySpecification('ratio', new PropertyType('number', false, null, null), false, '', '', null),
                'position' => new PropertySpecification('position', new PropertyType('integer', true, null, null), false, '', '', null),
            ],
            [],
        );
    }

    /**
     * Declares the properties imageType() lacks: a bare `object`, a union of primitives, and an `Entity`
     * reference that a produced MediaEntity is a subclass of.
     */
    private function referenceVariantsType(): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            'image',
            'Image',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [
                'media' => new PropertySpecification('media', new PropertyType(Entity::class, false, null, null), false, '', '', null),
                'mediaId' => new PropertySpecification('mediaId', new PropertyType('string', false, null, null), false, '', '', null),
                'payload' => new PropertySpecification('payload', new PropertyType('object', false, null, null), false, '', '', null),
                'identifier' => new PropertySpecification('identifier', new PropertyType(['string', 'integer'], false, null, null), false, '', '', null),
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
