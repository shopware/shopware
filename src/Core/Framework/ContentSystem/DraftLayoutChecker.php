<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Runs the well-formedness gate on a draft layout for the preview action. Delegates to {@see LayoutDiagnostics}
 * (intrinsic subset, no bound source) and maps the intrinsic-scope diagnostics back to constraint violations,
 * which it returns; it never throws. The preview action and the persistence gate thus share one diagnostics path
 * and the component-registration check cannot drift.
 *
 * WHY THE INTRINSIC SUBSET. A draft is checked before it has been
 * seeded with data, so a binding-scope analysis over-reports: every required input that a render would have filled
 * looks unfilled. Keeping only the intrinsic subset is what makes this usable on a draft at all.
 *
 * Mapping and binding faults are reported by diagnostics and do not prevent authors from previewing a draft.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DraftLayoutChecker
{
    public function __construct(
        private readonly LayoutDiagnostics $diagnostics,
    ) {
    }

    /**
     * @param list<StoredElement> $elements
     */
    public function check(array $elements): ConstraintViolationListInterface
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
