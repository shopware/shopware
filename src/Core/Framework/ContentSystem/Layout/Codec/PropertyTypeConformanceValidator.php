<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Codec;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;
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

            try {
                $stored = StoredValue::fromDecoded($raw);
            } catch (ContentSystemException $exception) {
                if (!ContentSystemException::isClientDefect($exception)) {
                    throw $exception;
                }

                $this->reportValueMismatch($constraint, $key, $type, $raw);

                continue;
            }

            if (!$type->admits($stored)) {
                $this->reportValueMismatch($constraint, $key, $type, $raw);

                continue;
            }

            $this->reportNonLanguageKeys($constraint, $key, $type->nonLanguageKeys($stored));
        }
    }

    private function reportValueMismatch(PropertyTypeConformance $constraint, string $key, PropertyType $type, mixed $raw): void
    {
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ key }}', $key)
            ->setParameter('{{ declaredType }}', $type->describe())
            ->setParameter('{{ actualType }}', get_debug_type($raw))
            ->atPath('[properties][' . $key . ']')
            ->addViolation();
    }

    /**
     * One violation per key that is not a language id, so a client can name and correct each. The keys come from
     * {@see PropertyType::nonLanguageKeys()}, the one owner of the key rule, which holds none for a
     * non-translatable property.
     *
     * @param list<string> $nonLanguageKeys
     */
    private function reportNonLanguageKeys(PropertyTypeConformance $constraint, string $key, array $nonLanguageKeys): void
    {
        foreach ($nonLanguageKeys as $languageKey) {
            $this->context->buildViolation($constraint->languageKeyMessage)
                ->setParameter('{{ key }}', $key)
                ->setParameter('{{ languageKey }}', $languageKey)
                ->atPath('[properties][' . $key . ']')
                ->addViolation();
        }
    }
}
