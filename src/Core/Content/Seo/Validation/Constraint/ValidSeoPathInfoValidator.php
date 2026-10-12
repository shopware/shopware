<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Validation\Constraint;

use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @internal
 */
#[Package('inventory')]
class ValidSeoPathInfoValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidSeoPathInfo) {
            throw SeoException::unexpectedType($constraint, ValidSeoPathInfo::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!\is_string($value)) {
            $this->context->buildViolation(ValidSeoPathInfo::INVALID_TYPE_MESSAGE)
                ->addViolation();

            return;
        }

        if (!ValidSeoPathInfo::containsDisallowedCharacters($value)) {
            return;
        }

        $this->context->buildViolation($constraint->getMessage())
            ->setParameter('{{ path }}', $this->formatValue($value))
            ->setCode(ValidSeoPathInfo::INVALID_CHARACTERS)
            ->addViolation();
    }
}
