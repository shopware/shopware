<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Lean draft-layout validation for the preview action. Delegates to {@see LayoutDiagnostics} (intrinsic subset,
 * no bound source) and maps the intrinsic-scope errors back to constraint violations, so the preview action and
 * the persistence gate share one diagnostics path and the component-registration check cannot drift.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentLayoutValidator
{
    public function __construct(
        private readonly LayoutDiagnostics $diagnostics,
    ) {
    }

    /**
     * @param list<ContentElement> $elements
     */
    public function validate(array $elements): ConstraintViolationListInterface
    {
        $violations = new ConstraintViolationList();

        foreach ($this->diagnostics->analyze($elements, null)->report->intrinsicErrors() as $violation) {
            $violations->add($this->toConstraintViolation($violation));
        }

        return $violations;
    }

    private function toConstraintViolation(Violation $violation): ConstraintViolation
    {
        return new ConstraintViolation(
            $violation->message,
            null,
            [],
            null,
            $violation->elementId,
            null,
            null,
            $violation->code->value,
        );
    }
}
