<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Codec;

use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
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

            // A null value is admissible under every primitive: whether a key may be absent or null is the
            // required-input rule's business, not this one's.
            if ($specification === null || $raw === null) {
                continue;
            }

            $types = $this->enforceableTypes($specification->type());

            if ($types === null || $this->matchesAny($raw, $types)) {
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

    /**
     * The primitive types a value must satisfy at least one of, or `null` when the declaration constrains
     * nothing: a bare `object` or an FQCN admits whatever the client authored, and so does a union carrying
     * either, because that member alone accepts every value.
     *
     * A union's declared type is an array, so {@see PropertyType::isPrimitive()} answers false for every one of
     * them; the members are tested against {@see PropertyType::PRIMITIVE_TYPES} here instead.
     *
     * @return list<string>|null
     */
    private function enforceableTypes(PropertyType $type): ?array
    {
        $declared = $type->type();

        if (\is_string($declared)) {
            return \in_array($declared, PropertyType::PRIMITIVE_TYPES, true) ? [$declared] : null;
        }

        if ($declared === []) {
            return null;
        }

        foreach ($declared as $member) {
            if (!\in_array($member, PropertyType::PRIMITIVE_TYPES, true)) {
                return null;
            }
        }

        return $declared;
    }

    /**
     * @param list<string> $types
     */
    private function matchesAny(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            if ($this->matches($value, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `number` admits an integer as well as a float — JSON carries no distinction a client can be held to —
     * while `integer` admits only an integer.
     */
    private function matches(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => \is_string($value),
            'integer' => \is_int($value),
            'number' => \is_int($value) || \is_float($value),
            'boolean' => \is_bool($value),
            default => false,
        };
    }
}
