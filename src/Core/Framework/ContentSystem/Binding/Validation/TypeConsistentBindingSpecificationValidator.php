<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Validation;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDtoCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @internal only for use by the content-system binding specifications
 */
#[Package('framework')]
final class TypeConsistentBindingSpecificationValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
        private readonly RootContextMapper $rootContextMapper,
        private readonly AbstractContentSystemDataLoaderMapResolver $mapResolver,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TypeConsistentBindingSpecification) {
            throw new UnexpectedTypeException($constraint, TypeConsistentBindingSpecification::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof BindingSpecificationDtoCollection) {
            throw new UnexpectedTypeException($value, BindingSpecificationDtoCollection::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        foreach ($value->bindings as $id => $dto) {
            $this->validateBinding((string) $id, $dto, $value->typeOverlay, $constraint);
        }
    }

    /**
     * @param array<string, ContentSystemElementTypeSpecification> $typeOverlay
     */
    private function validateBinding(string $id, BindingSpecificationDto $dto, array $typeOverlay, TypeConsistentBindingSpecification $constraint): void
    {
        $type = $this->resolveType($dto->type, $typeOverlay);

        if ($type === null) {
            $this->context->buildViolation($constraint->unknownTypeMessage)
                ->setParameter('{{ type }}', \is_string($dto->type) ? $dto->type : '')
                ->atPath($this->path($id, 'type'))
                ->addViolation();

            return;
        }

        $this->validateResolves($id, $dto, $type, $constraint);
        $this->validateInputs($id, $dto, $type, $constraint);
    }

    /**
     * Resolves the declared type against the overlay first, then the registry. The overlay carries types not yet
     * registered (an app's own types at install/validate time); a miss on both is an unknown type.
     *
     * @param array<string, ContentSystemElementTypeSpecification> $typeOverlay
     */
    private function resolveType(mixed $type, array $typeOverlay): ?ContentSystemElementTypeSpecification
    {
        if (!\is_string($type) || $type === '') {
            return null;
        }

        if (isset($typeOverlay[$type])) {
            return $typeOverlay[$type];
        }

        if ($this->registry->has($type)) {
            return $this->registry->get($type);
        }

        return null;
    }

    private function validateResolves(string $id, BindingSpecificationDto $value, ContentSystemElementTypeSpecification $type, TypeConsistentBindingSpecification $constraint): void
    {
        if (!\is_array($value->resolves)) {
            return;
        }

        foreach ($value->resolves as $key => $entry) {
            $this->validateResolvesEntry($id, (string) $key, $entry, $type, $constraint);
        }
    }

    private function validateResolvesEntry(string $id, string $key, mixed $entry, ContentSystemElementTypeSpecification $type, TypeConsistentBindingSpecification $constraint): void
    {
        if (!\is_array($entry)) {
            return;
        }

        $loader = $entry['loader'] ?? null;

        if (!\is_string($loader) || $loader === '') {
            if (\array_key_exists('context', $entry)) {
                $this->context->buildViolation($constraint->resolvesEntryContextFormMessage)
                    ->setParameter('{{ key }}', $key)
                    ->atPath($this->path($id, 'resolves[' . $key . ']'))
                    ->addViolation();
            }

            return;
        }

        $property = $type->properties()[$key] ?? null;
        $declaredType = $property?->type()->type();

        if ($property === null || $property->type()->isPrimitive() || !\is_string($declaredType) || $declaredType === 'object') {
            $this->context->buildViolation($constraint->resolvesEntryNotReferencePropertyMessage)
                ->setParameter('{{ key }}', $key)
                ->setParameter('{{ type }}', $type->name())
                ->atPath($this->path($id, 'resolves[' . $key . ']'))
                ->addViolation();

            return;
        }

        $config = $entry['config'] ?? [];
        $config = \is_array($config) ? $config : [];

        $configObject = $this->decodeConfig($id, $key, $loader, $config, $constraint);

        if ($configObject === null) {
            return;
        }

        $producedType = $this->resolveProducedType($id, $key, $loader, $configObject, $constraint);

        if ($producedType === null) {
            return;
        }

        if (!is_a($producedType, $declaredType, true)) {
            $this->context->buildViolation($constraint->resolvesEntryNotAssignableMessage)
                ->setParameter('{{ key }}', $key)
                ->setParameter('{{ producedType }}', $producedType)
                ->setParameter('{{ declaredType }}', $declaredType)
                ->atPath($this->path($id, 'resolves[' . $key . ']'))
                ->addViolation();

            return;
        }

        $this->validatePropertyReferenceKeys($id, $key, $loader, $config, $type, $constraint);
    }

    /**
     * Every config key of kind `propertyReference` (per the loader's config specification) whose configured value
     * is a string must name either an undeclared key (the resolvedBy storage key) or a declared primitive
     * property of the declared type. A declared non-primitive property is a violation for every loader. Reaching
     * this point means `decodeConfig()` and `resolveProducedType()` both succeeded, so the loader is a registered
     * data loader and thus present in the map, so `configSpecificationFor()` cannot throw here.
     *
     * @param array<string, mixed> $config
     */
    private function validatePropertyReferenceKeys(string $id, string $key, string $loader, array $config, ContentSystemElementTypeSpecification $type, TypeConsistentBindingSpecification $constraint): void
    {
        $specification = $this->mapResolver->resolve()->configSpecificationFor($loader);

        foreach ($specification->keys as $configKey) {
            if ($configKey->kind !== ConfigKeyKind::PropertyReference) {
                continue;
            }

            $configured = $config[$configKey->name] ?? null;

            if (!\is_string($configured)) {
                continue;
            }

            $property = $type->properties()[$configured] ?? null;

            if ($property === null || $property->type()->isPrimitive()) {
                continue;
            }

            if ($configKey->referencedType === 'object') {
                continue;
            }

            $this->context->buildViolation($constraint->resolvesEntryPropertyReferenceNotPrimitiveMessage)
                ->setParameter('{{ configKey }}', $configKey->name)
                ->setParameter('{{ property }}', $configured)
                ->setParameter('{{ type }}', $type->name())
                ->atPath($this->path($id, 'resolves[' . $key . '].config.' . $configKey->name))
                ->addViolation();
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function decodeConfig(string $id, string $key, string $loader, array $config, TypeConsistentBindingSpecification $constraint): ?AbstractContentDataLoaderConfig
    {
        try {
            return $this->configSerializerProvider->decode($loader, $config);
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            if ($exception->getErrorCode() === ContentSystemException::CONFIG_SERIALIZER_NOT_REGISTERED) {
                $this->context->buildViolation($constraint->resolvesEntryLoaderNotRegisteredMessage)
                    ->setParameter('{{ key }}', $key)
                    ->setParameter('{{ loader }}', $loader)
                    ->atPath($this->path($id, 'resolves[' . $key . ']'))
                    ->addViolation();

                return null;
            }

            $this->context->buildViolation($constraint->resolvesEntryConfigMessage)
                ->setParameter('{{ key }}', $key)
                ->setParameter('{{ reason }}', $exception->getMessage())
                ->atPath($this->path($id, 'resolves[' . $key . '].config'))
                ->addViolation();

            return null;
        }
    }

    private function resolveProducedType(string $id, string $key, string $loader, AbstractContentDataLoaderConfig $configObject, TypeConsistentBindingSpecification $constraint): ?string
    {
        try {
            return $this->rootContextMapper->resolveType(new DataRequirement($key, $loader, $configObject));
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            $this->context->buildViolation($constraint->resolvesEntryConfigMessage)
                ->setParameter('{{ key }}', $key)
                ->setParameter('{{ reason }}', $exception->getMessage())
                ->atPath($this->path($id, 'resolves[' . $key . '].config'))
                ->addViolation();

            return null;
        }
    }

    private function validateInputs(string $id, BindingSpecificationDto $value, ContentSystemElementTypeSpecification $type, TypeConsistentBindingSpecification $constraint): void
    {
        if (!\is_array($value->inputs)) {
            return;
        }

        foreach ($value->inputs as $key => $entry) {
            $this->validateInputsEntry($id, (string) $key, $entry, $type, $constraint);
        }
    }

    private function validateInputsEntry(string $id, string $key, mixed $entry, ContentSystemElementTypeSpecification $type, TypeConsistentBindingSpecification $constraint): void
    {
        if (!\is_array($entry)) {
            return;
        }

        $property = $type->properties()[$key] ?? null;

        if ($property === null || !$property->type()->isPrimitive()) {
            $this->context->buildViolation($constraint->inputsEntryNotPrimitivePropertyMessage)
                ->setParameter('{{ key }}', $key)
                ->setParameter('{{ type }}', $type->name())
                ->atPath($this->path($id, 'inputs[' . $key . ']'))
                ->addViolation();

            return;
        }

        if (!\array_key_exists('default', $entry)) {
            return;
        }

        $default = $entry['default'];

        if ($default === null) {
            return;
        }

        if (!\is_scalar($default)) {
            return;
        }

        $primitiveType = $this->getSinglePrimitiveType($property);

        if ($primitiveType !== null && $this->matchesType($default, $primitiveType)) {
            return;
        }

        $this->context->buildViolation($constraint->inputsEntryDefaultTypeMessage)
            ->setParameter('{{ key }}', $key)
            ->setParameter('{{ type }}', $primitiveType ?? '')
            ->atPath($this->path($id, 'inputs[' . $key . '].default'))
            ->addViolation();
    }

    private function matchesType(string|int|float|bool $value, string $type): bool
    {
        return match ($type) {
            'string' => \is_string($value),
            'integer' => \is_int($value),
            'boolean' => \is_bool($value),
            'number' => \is_int($value) || \is_float($value),
            default => false,
        };
    }

    private function getSinglePrimitiveType(PropertySpecification $property): ?string
    {
        $declaredType = $property->type()->type();
        $types = \is_string($declaredType) ? [$declaredType] : array_values($declaredType);

        if (\count($types) !== 1) {
            return null;
        }

        $resolvedType = $types[0];

        if (!\in_array($resolvedType, PropertyType::PRIMITIVE_TYPES, true)) {
            return null;
        }

        return $resolvedType;
    }

    private function path(string $id, string $suffix): string
    {
        return 'bindings[' . $id . '].' . $suffix;
    }
}
