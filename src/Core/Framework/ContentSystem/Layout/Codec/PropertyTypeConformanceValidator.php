<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Codec;

use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * The registry lookup is `has()`-guarded and silent on a miss: an unregistered component is
 * {@see LayoutDiagnostics}' to report, and an unguarded
 * `get()` would throw `elementTypeNotFound` — a structured 404, but the wrong status and error code for the
 * write-constraint pass, which should reject with a 400. A tree naming an unregistered component is still
 * refused, by the resolvability gate.
 *
 * @internal only for use by the content-system stored-tree write path
 */
#[Package('framework')]
final class PropertyTypeConformanceValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PropertyTypeConformance) {
            throw new UnexpectedTypeException($constraint, PropertyTypeConformance::class); // @phpstan-ignore shopware.domainException (Symfony ConstraintValidator convention)
        }

        if (!\is_array($value)) {
            return;
        }

        $component = $value['component'] ?? null;
        $properties = $value['properties'] ?? null;

        if (!\is_string($component) || !\is_array($properties) || !$this->registry->has($component)) {
            return;
        }

        $declared = $this->registry->get($component)->properties();

        foreach ($properties as $key => $raw) {
            $specification = $declared[$key] ?? null;

            if ($specification === null) {
                continue;
            }

            $types = $specification->type()->enforceableTypes();

            if ($types === null || $specification->type()->admits($raw)) {
                continue;
            }

            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ key }}', (string) $key)
                ->setParameter('{{ declaredType }}', implode('|', $types))
                ->setParameter('{{ actualType }}', get_debug_type($raw))
                ->atPath('[properties][' . $key . ']')
                ->addViolation();
        }
    }
}
