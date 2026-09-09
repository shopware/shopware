<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset\Validation;

use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @internal only for use by the content-system layout presets
 */
#[Package('framework')]
final class LayoutPresetSpecificationValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof LayoutPresetSpecification) {
            throw new UnexpectedTypeException($constraint, LayoutPresetSpecification::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof LayoutPresetSpecificationDto) {
            throw new UnexpectedTypeException($value, LayoutPresetSpecificationDto::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!array_is_list($value->layout)) {
            $this->context->buildViolation($constraint->layoutMessage)
                ->atPath('layout')
                ->addViolation();
        }
    }
}
