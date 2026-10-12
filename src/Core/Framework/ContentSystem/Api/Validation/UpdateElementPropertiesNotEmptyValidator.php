<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api\Validation;

use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutUpdateElementPropertiesRequest;
use Shopware\Core\Framework\ContentSystem\Api\UpdateElementPropertiesRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * A request writing nothing and removing nothing has no edit to apply, so it is refused here rather than
 * answered with an unchanged tree. The violation sits on `values` — the property path never reaches the
 * HTTP body (the envelope 400 serializes messages only) and is consumed by the DTO tests alone. Any other
 * host object is a wiring mistake and throws rather than validating nothing.
 *
 * @internal only for use by the content-system mutation request DTOs
 */
#[Package('framework')]
final class UpdateElementPropertiesNotEmptyValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UpdateElementPropertiesNotEmpty) {
            throw new UnexpectedTypeException($constraint, UpdateElementPropertiesNotEmpty::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!$value instanceof UpdateElementPropertiesRequest && !$value instanceof ContentLayoutUpdateElementPropertiesRequest) {
            throw new UnexpectedTypeException($value, UpdateElementPropertiesRequest::class . '|' . ContentLayoutUpdateElementPropertiesRequest::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if ($value->values !== [] || $value->removeKeys !== []) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath('values')
            ->setInvalidValue($value->values)
            ->setCode(UpdateElementPropertiesNotEmpty::EMPTY_REQUEST_ERROR)
            ->addViolation();
    }
}
