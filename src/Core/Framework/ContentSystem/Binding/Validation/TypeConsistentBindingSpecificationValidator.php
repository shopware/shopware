<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Validation;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
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
    private const PRIMITIVE_TYPES = ['string', 'integer', 'boolean', 'number'];

    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
        private readonly RootContextMapper $rootContextMapper,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TypeConsistentBindingSpecification) {
            throw new UnexpectedTypeException($constraint, TypeConsistentBindingSpecification::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof BindingSpecificationDto) {
            throw new UnexpectedTypeException($value, BindingSpecificationDto::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!\is_string($value->type) || !$this->registry->has($value->type)) {
            $this->context->buildViolation($constraint->unknownTypeMessage)
                ->setParameter('{{ type }}', \is_string($value->type) ? $value->type : '')
                ->atPath('type')
                ->addViolation();

            return;
        }

        $type = $this->registry->get($value->type);

        $this->validateResolves($value, $type, $constraint);
        $this->validateInputs($value, $type, $constraint);
    }

    private function validateResolves(BindingSpecificationDto $value, ContentSystemElementTypeSpecification $type, TypeConsistentBindingSpecification $constraint): void
    {
        if (!\is_array($value->resolves)) {
            return;
        }

        foreach ($value->resolves as $key => $entry) {
            $this->validateResolvesEntry((string) $key, $entry, $type, $constraint);
        }
    }

    private function validateResolvesEntry(string $key, mixed $entry, ContentSystemElementTypeSpecification $type, TypeConsistentBindingSpecification $constraint): void
    {
        if (!\is_array($entry)) {
            return;
        }

        $loader = $entry['loader'] ?? null;

        if (!\is_string($loader) || $loader === '') {
            if (\array_key_exists('context', $entry)) {
                $this->context->buildViolation($constraint->resolvesEntryContextFormMessage)
                    ->setParameter('{{ key }}', $key)
                    ->atPath('resolves[' . $key . ']')
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
                ->atPath('resolves[' . $key . ']')
                ->addViolation();

            return;
        }

        $config = $entry['config'] ?? [];
        $config = \is_array($config) ? $config : [];

        $configObject = $this->decodeConfig($key, $loader, $config, $constraint);

        if ($configObject === null) {
            return;
        }

        $producedType = $this->resolveProducedType($key, $loader, $configObject, $constraint);

        if ($producedType === null) {
            return;
        }

        if (!is_a($producedType, $declaredType, true)) {
            $this->context->buildViolation($constraint->resolvesEntryNotAssignableMessage)
                ->setParameter('{{ key }}', $key)
                ->setParameter('{{ producedType }}', $producedType)
                ->setParameter('{{ declaredType }}', $declaredType)
                ->atPath('resolves[' . $key . ']')
                ->addViolation();

            return;
        }

        if ($loader !== EntityLoader::SOURCE) {
            return;
        }

        $this->validateEntityLoaderProperty($key, $config, $type, $constraint);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function decodeConfig(string $key, string $loader, array $config, TypeConsistentBindingSpecification $constraint): ?AbstractContentDataLoaderConfig
    {
        try {
            return $this->configSerializerProvider->decode($loader, $config);
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            $this->context->buildViolation($constraint->resolvesEntryConfigMessage)
                ->setParameter('{{ key }}', $key)
                ->setParameter('{{ reason }}', $exception->getMessage())
                ->atPath('resolves[' . $key . '].config')
                ->addViolation();

            return null;
        }
    }

    private function resolveProducedType(string $key, string $loader, AbstractContentDataLoaderConfig $configObject, TypeConsistentBindingSpecification $constraint): ?string
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
                ->atPath('resolves[' . $key . '].config')
                ->addViolation();

            return null;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateEntityLoaderProperty(string $key, array $config, ContentSystemElementTypeSpecification $type, TypeConsistentBindingSpecification $constraint): void
    {
        $property = $config['property'] ?? null;

        if (!\is_string($property)) {
            return;
        }

        $entityProperty = $type->properties()[$property] ?? null;

        if ($entityProperty !== null && $entityProperty->type()->isPrimitive()) {
            return;
        }

        $this->context->buildViolation($constraint->resolvesEntryEntityPropertyNotPrimitiveMessage)
            ->setParameter('{{ key }}', $key)
            ->setParameter('{{ type }}', $type->name())
            ->atPath('resolves[' . $key . '].config.property')
            ->addViolation();
    }

    private function validateInputs(BindingSpecificationDto $value, ContentSystemElementTypeSpecification $type, TypeConsistentBindingSpecification $constraint): void
    {
        if (!\is_array($value->inputs)) {
            return;
        }

        foreach ($value->inputs as $key => $entry) {
            $this->validateInputsEntry((string) $key, $entry, $type, $constraint);
        }
    }

    private function validateInputsEntry(string $key, mixed $entry, ContentSystemElementTypeSpecification $type, TypeConsistentBindingSpecification $constraint): void
    {
        if (!\is_array($entry)) {
            return;
        }

        $property = $type->properties()[$key] ?? null;

        if ($property === null || !$property->type()->isPrimitive()) {
            $this->context->buildViolation($constraint->inputsEntryNotPrimitivePropertyMessage)
                ->setParameter('{{ key }}', $key)
                ->setParameter('{{ type }}', $type->name())
                ->atPath('inputs[' . $key . ']')
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
            ->atPath('inputs[' . $key . '].default')
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

        if (!\in_array($resolvedType, self::PRIMITIVE_TYPES, true)) {
            return null;
        }

        return $resolvedType;
    }
}
