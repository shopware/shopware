<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Validation;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @internal only for use by the content-system binding specifications
 */
#[Package('framework')]
final class WellFormedBindingSpecificationValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof WellFormedBindingSpecification) {
            throw new UnexpectedTypeException($constraint, WellFormedBindingSpecification::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof BindingSpecificationDto) {
            throw new UnexpectedTypeException($value, BindingSpecificationDto::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        $this->validateType($value, $constraint);
        $this->validateLabel($value, $constraint);
        $this->validateResolves($value, $constraint);
        $this->validateInputs($value, $constraint);
    }

    private function validateType(BindingSpecificationDto $value, WellFormedBindingSpecification $constraint): void
    {
        if (\is_string($value->type) && $value->type !== '') {
            return;
        }

        $this->context->buildViolation($constraint->typeBlankMessage)
            ->atPath('type')
            ->addViolation();
    }

    private function validateLabel(BindingSpecificationDto $value, WellFormedBindingSpecification $constraint): void
    {
        if (\is_string($value->label) && $value->label !== '') {
            return;
        }

        $this->context->buildViolation($constraint->labelBlankMessage)
            ->atPath('label')
            ->addViolation();
    }

    private function validateResolves(BindingSpecificationDto $value, WellFormedBindingSpecification $constraint): void
    {
        // Absent in the YAML body (null) means no reference wiring: valid and empty. A present non-array is rejected.
        if ($value->resolves === null) {
            return;
        }

        if (!\is_array($value->resolves)) {
            $this->context->buildViolation($constraint->resolvesArrayMessage)
                ->atPath('resolves')
                ->addViolation();

            return;
        }

        foreach ($value->resolves as $key => $entry) {
            $this->validateResolvesEntry((string) $key, $entry, $constraint);
        }
    }

    private function validateResolvesEntry(string $key, mixed $entry, WellFormedBindingSpecification $constraint): void
    {
        if (!\is_array($entry)) {
            $this->context->buildViolation($constraint->resolvesEntryArrayMessage)
                ->setParameter('{{ key }}', $key)
                ->atPath('resolves[' . $key . ']')
                ->addViolation();

            return;
        }

        $loader = $entry['loader'] ?? null;
        if (!\is_string($loader) || $loader === '') {
            $this->context->buildViolation($constraint->resolvesEntryLoaderMessage)
                ->setParameter('{{ key }}', $key)
                ->atPath('resolves[' . $key . '].loader')
                ->addViolation();
        }

        $config = $entry['config'] ?? null;
        if ($config !== null && !\is_array($config)) {
            $this->context->buildViolation($constraint->resolvesEntryConfigMessage)
                ->setParameter('{{ key }}', $key)
                ->atPath('resolves[' . $key . '].config')
                ->addViolation();
        }
    }

    private function validateInputs(BindingSpecificationDto $value, WellFormedBindingSpecification $constraint): void
    {
        // Absent in the YAML body (null) means no residual inputs: valid and empty. A present non-array is rejected.
        if ($value->inputs === null) {
            return;
        }

        if (!\is_array($value->inputs)) {
            $this->context->buildViolation($constraint->inputsArrayMessage)
                ->atPath('inputs')
                ->addViolation();

            return;
        }

        foreach ($value->inputs as $key => $entry) {
            $this->validateInputsEntry((string) $key, $entry, $constraint);
        }
    }

    private function validateInputsEntry(string $key, mixed $entry, WellFormedBindingSpecification $constraint): void
    {
        if (!\is_array($entry)) {
            $this->context->buildViolation($constraint->inputsEntryArrayMessage)
                ->setParameter('{{ key }}', $key)
                ->atPath('inputs[' . $key . ']')
                ->addViolation();

            return;
        }

        if (!\array_key_exists('required', $entry) || !\is_bool($entry['required'])) {
            $this->context->buildViolation($constraint->inputsEntryRequiredMessage)
                ->setParameter('{{ key }}', $key)
                ->atPath('inputs[' . $key . '].required')
                ->addViolation();
        }

        if (!\array_key_exists('default', $entry)) {
            return;
        }

        $default = $entry['default'];
        if ($default !== null && !\is_scalar($default)) {
            $this->context->buildViolation($constraint->inputsEntryDefaultMessage)
                ->setParameter('{{ key }}', $key)
                ->atPath('inputs[' . $key . '].default')
                ->addViolation();
        }
    }
}
