<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Constraint;

use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

#[Package('framework')]
class NoHtmlValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(private readonly HtmlSanitizer $sanitizer)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoHtml) {
            throw FrameworkException::unexpectedType($constraint, NoHtml::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            return;
        }

        $value = (string) $value;

        if ($this->sanitizer->stripTags($value) === $value) {
            return;
        }

        $this->context->buildViolation($constraint->getMessage())
            ->setCode(NoHtml::CONTAINS_HTML_ERROR)
            ->addViolation();
    }
}
