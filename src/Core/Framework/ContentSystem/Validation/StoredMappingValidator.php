<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingProblem;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The data-mapping half of the content_layout write gate: it refuses a write carrying a mapping that
 * {@see StoredMappingInspector} finds inadmissible.
 *
 * The rules themselves live in the inspector, because {@see LayoutDiagnostics} applies the same set to
 * report mapping problems on the diagnose and draft mutation routes. This class is only the write-path
 * rendering of them — one `ConstraintViolation` per problem, carrying the specific error code so the
 * Administration can tell a non-mappable property from an uncatalogued path from a type mismatch.
 *
 * It stays a separate step from the analysis the gate runs rather than folding into it, for the reason the
 * inspector's own docblock gives: the rules are keyed on the root SOURCE, and `LayoutDiagnostics::analyze()`
 * is handed the resolved root CONTEXT, which cannot be turned back into a source id. The write validator
 * has the source to hand and passes it; the two surfaces therefore report the same findings through
 * different codes, and neither can drift from the other because neither owns the rule.
 *
 * @internal
 */
#[Package('framework')]
final class StoredMappingValidator
{
    public function __construct(
        private readonly StoredMappingInspector $inspector,
    ) {
    }

    /**
     * @param list<StoredElement> $roots
     */
    public function validate(array $roots, string $rootSource): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();

        foreach ($this->inspector->inspect($roots, $rootSource) as $problem) {
            $violations->add($this->toViolation($problem));
        }

        return $violations;
    }

    /**
     * Keyed on the element and the mapped PROPERTY rather than on the consumer key, matching
     * {@see ViolationConstraintMapper}'s `/{elementId}/{key}` shape, so the Administration highlights the
     * control the author acted on rather than a wiring key it does not render.
     */
    private function toViolation(MappingProblem $problem): ConstraintViolation
    {
        return new ConstraintViolation(
            $problem->exception->getMessage(),
            $problem->exception->getMessage(),
            [],
            null,
            \sprintf('/%s/%s', $problem->elementId, $problem->propertyKey),
            $problem->sourcePath,
            null,
            $problem->exception->getErrorCode(),
        );
    }
}
