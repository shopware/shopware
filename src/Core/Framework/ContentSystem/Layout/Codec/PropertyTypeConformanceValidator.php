<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Codec;

use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Two rules per declared property. The value rule is {@see PropertyType::admits()}, the one conformance
 * predicate, so this pass keeps no match table of its own. The key rule applies to a translatable property
 * alone: its value is a language map, and every key must be a language id in lowercase UUID hex.
 *
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

            $type = $specification->type();

            // fromDecoded() throws for a non-finite float (a JSON 1e400 decodes to INF) and nothing catches
            // it here; that 500 is the accepted limitation in Api/docs/mutation-errors.md. A list-shaped
            // payload never carries one this far: the write's first decode already admitted its values
            // (see Layout/Field/README.md).
            if (!$type->admits(StoredValue::fromDecoded($raw))) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ key }}', (string) $key)
                    ->setParameter('{{ declaredType }}', $this->renderDeclaredType($type))
                    ->setParameter('{{ actualType }}', get_debug_type($raw))
                    ->atPath('[properties][' . $key . ']')
                    ->addViolation();

                continue;
            }

            if (!$type->translatable()) {
                continue;
            }

            if (!\is_array($raw)) {
                // Unreachable: admits() already established a translatable value is a map before this point.
                // The check exists only so PHPStan narrows $raw for reportNonLanguageKeys().
                continue;
            }

            $this->reportNonLanguageKeys($constraint, (string) $key, $raw);
        }
    }

    /**
     * One violation per key that is not a language id, so a client can name and correct each. Only the key
     * format is judged: whether the id names an existing language is a diagnostics warning, never a write
     * rejection. No separate case check accompanies the domain test — {@see Uuid::VALID_PATTERN} is anchored
     * lowercase-only hex, so an upper-case key already fails it.
     *
     * @param array<array-key, mixed> $map
     */
    private function reportNonLanguageKeys(PropertyTypeConformance $constraint, string $key, array $map): void
    {
        foreach (array_keys($map) as $rawKey) {
            $languageKey = (string) $rawKey;

            if (Uuid::isValid($languageKey)) {
                continue;
            }

            $this->context->buildViolation($constraint->languageKeyMessage)
                ->setParameter('{{ key }}', $key)
                ->setParameter('{{ languageKey }}', $languageKey)
                ->atPath('[properties][' . $key . ']')
                ->addViolation();
        }
    }

    /**
     * The declared type as the message names it. The translatable flag is spelled out because the same
     * `string` declaration admits a bare string without it and only a language map with it, so the flag is
     * what a client needs to read the rejection.
     */
    private function renderDeclaredType(PropertyType $type): string
    {
        $declared = implode('|', (array) $type->type());

        if (!$type->translatable()) {
            return $declared;
        }

        return $declared . ' (translatable)';
    }
}
