<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingProblem;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\ContentSystem\Validation\StoredMappingValidator;
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
 * WHY THE INTRINSIC SUBSET, and why inline mapping is the exception to it. A draft is checked before it has been
 * seeded with data, so a binding-scope analysis over-reports: every required input that a render would have filled
 * looks unfilled. Keeping only the intrinsic subset is what makes this usable on a draft at all.
 *
 * Inline mapping problems are binding-scoped and would be discarded with the rest, and here that is not
 * conservative but unsafe. A `{{map:path}}` token is expanded at render time by reading the path out of the root
 * entity, and the render path performs no admissibility check of its own — it resolves what the catalogue offers and
 * leaves everything else verbatim. This gate and the `content_layout` write gate are therefore the only two places
 * an inadmissible token is refused, and the preview reaches a renderer without passing the write gate. So the inline
 * rules are pulled in explicitly, through {@see StoredMappingInspector::inspectInline()} rather than by widening to
 * all binding errors: they are the one binding-scope rule set that an unseeded draft does not distort, because
 * whether a path is catalogued has nothing to do with whether the draft has data yet.
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
        private readonly StoredMappingInspector $mappingInspector,
    ) {
    }

    /**
     * @param list<StoredElement> $elements
     * @param string|null $rootSource the layout's root source; null means no catalogue applies, so no token could
     *                                have been admissible and the inline pass has nothing to judge against
     */
    public function check(array $elements, ?string $rootSource = null): ConstraintViolationListInterface
    {
        $violations = new ConstraintViolationList();

        foreach ($this->diagnostics->analyze($elements, null)->report->intrinsicErrors() as $violation) {
            $violations->add($this->toConstraintViolation($violation));
        }

        if ($rootSource === null) {
            return $violations;
        }

        foreach ($this->mappingInspector->inspectInline($elements, $rootSource) as $problem) {
            $violations->add($this->mappingProblemToConstraintViolation($problem));
        }

        return $violations;
    }

    /**
     * Keyed on the property rather than the element, matching how {@see StoredMappingValidator} reports the same
     * problems on the write path, so an author sees one message wherever they hit the rule.
     */
    private function mappingProblemToConstraintViolation(MappingProblem $problem): ConstraintViolation
    {
        return new ConstraintViolation(
            $problem->exception->getMessage(),
            null,
            [],
            null,
            $problem->elementId . '/' . $problem->propertyKey,
            null,
            null,
            $problem->exception->getErrorCode(),
        );
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
