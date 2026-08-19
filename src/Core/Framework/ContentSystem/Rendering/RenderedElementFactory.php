<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * Mints the {@see RenderedElement} for one {@see StoredElement}, and owns the rule deciding which of that
 * element's keys survive onto the rendered side.
 *
 * A rendered element's properties are derived, not inherited. A stored element may carry any number of
 * keys; only those in the union of four members appear on the rendered element, and everything else is
 * dropped:
 *
 * - declared primitive properties — the primitive-typed keys the element's type declares, carrying the
 *   stored value under that key
 * - requirement keys — the keys of the element's data requirements, carrying the resolved loader value
 * - delivered context keys — the keys context was actually delivered under, carrying the delivered value
 * - distribution-referenced keys — stored keys a parent's distribution config dereferences by name (today
 *   only {@see \Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig::$keyProperty}),
 *   carrying the stored value under that key, unless that key is a declared reference property
 *
 * "Delivered" means delivered: a consumer key the element declares but which no ancestor fulfilled is not
 * a member, so it does not appear at all rather than appearing as null. An explicit null on a rendered
 * property means a resolution ran and found nothing, and it has exactly two producers: a loader's
 * {@see \Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult::notFound()},
 * and a context delivery that resolved to nothing (an under-supplied distribution strategy handing an
 * unmatched consumer null, or an optional dotted consumer key whose value cannot be traversed). Neither
 * authoring nor non-delivery is among them — an authored null and an undelivered consumer key are both
 * absent. Keeping present-null and key-absent apart is the point of the distinction.
 *
 * The same reading applies to a declared key with no stored value: the member carries "the stored value
 * under that key", so a declared reference property nothing filled is absent rather than null. An authored
 * null counts as no stored value for the same reason — the two stored-value members skip a null-variant
 * {@see StoredValue} rather than rendering it — which is what keeps authoring out of the producer set
 * above.
 *
 * This is also where a {@see StoredValue} is unwrapped on the way to the rendered side: a wrapped value must
 * never reach the rendered element. It is not the module's only unwrap — {@see ElementDataResolver} unwraps
 * the same map to feed the loader input resolver — but it is the one this path goes through.
 *
 * @internal
 */
#[Package('framework')]
final readonly class RenderedElementFactory
{
    public function __construct(
        private AbstractContentSystemElementTypeRegistry $typeRegistry,
    ) {
    }

    /**
     * Members are written lowest tier first, so a higher tier overwrites what a lower one wrote. Highest
     * wins: delivered context, then loader-resolved, then declared primitive, then distribution-referenced.
     * The first three reproduce the order the current pipeline writes in. The last two cannot differ in
     * practice — both copy the same stored value — so their relative order is fixed purely for
     * determinism, and carries no behavioural meaning.
     *
     * @param array<string, mixed> $resolvedLoaderValues keyed by requirement key
     * @param array<string, mixed> $deliveredContext keyed by the key context was delivered under
     * @param list<string> $distributionReferencedKeys stored keys a parent's distribution config names
     * @param array<string, list<RenderedElement>> $slots already-minted children
     */
    public function create(
        StoredElement $stored,
        array $resolvedLoaderValues,
        array $deliveredContext,
        array $distributionReferencedKeys,
        array $slots,
    ): RenderedElement {
        $storedProperties = $stored->properties();
        $declaredProperties = $this->declaredProperties($stored->component);
        $properties = [];

        foreach ($distributionReferencedKeys as $key) {
            if ($this->isDeclaredReference($declaredProperties, $key)) {
                continue;
            }

            if ($this->carriesAValue($storedProperties, $key)) {
                $properties[$key] = $storedProperties[$key]->jsonSerialize();
            }
        }

        foreach ($this->declaredPrimitiveKeys($declaredProperties) as $key) {
            if ($this->carriesAValue($storedProperties, $key)) {
                $properties[$key] = $storedProperties[$key]->jsonSerialize();
            }
        }

        foreach (array_keys($stored->dataRequirements) as $key) {
            if (\array_key_exists($key, $resolvedLoaderValues)) {
                $properties[$key] = $resolvedLoaderValues[$key];
            }
        }

        foreach ($deliveredContext as $key => $value) {
            $properties[$key] = $value;
        }

        return new RenderedElement($stored->id, $stored->component, $properties, $slots, $stored->style);
    }

    /**
     * The skeleton shape: structure only, no properties at all.
     *
     * @param array<string, list<RenderedElement>> $slots
     */
    public function createStructural(StoredElement $stored, array $slots): RenderedElement
    {
        return new RenderedElement($stored->id, $stored->component, [], $slots, $stored->style);
    }

    /**
     * The two stored-value tiers admit a key only when the stored map holds it and the value it holds is
     * not the null variant. An authored null is no value, so it contributes no key, which is what keeps
     * authoring out of the producer set for a rendered null — that null always means some resolution ran
     * and found nothing, never that someone typed one. It is also the reading the resolvability gate
     * already takes: {@see \Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics} counts a
     * required property holding an authored null as unresolved, so a value the gate refuses to call
     * satisfied must not render as a satisfied one.
     *
     * @param array<string, StoredValue> $storedProperties
     */
    private function carriesAValue(array $storedProperties, string $key): bool
    {
        return isset($storedProperties[$key]) && !$storedProperties[$key]->isNull();
    }

    /**
     * The shared invariant of both stored-value tiers: a stored value under a declared reference property
     * never reaches the rendered element, whichever tier would otherwise have carried it. A reference
     * property's stored value is an id the pipeline resolves, and serving the raw id in the place a hydrated
     * entity belongs would hand the Twig filter chain a string where it expects the object. The two tiers
     * express the one rule differently only because their defaults about declaredness are opposite: this
     * tier admits by default and so excludes declared references, while the declared tier admits nothing by
     * default and so selects declared primitives.
     *
     * An undeclared key is not a declared reference and passes. That is the ordinary case for this tier: a
     * keyed distribution's `keyProperty` names a stored key that is usually declared by nothing at all.
     *
     * @param array<string, PropertySpecification> $declaredProperties
     */
    private function isDeclaredReference(array $declaredProperties, string $key): bool
    {
        return isset($declaredProperties[$key]) && !$declaredProperties[$key]->type()->isPrimitive();
    }

    /**
     * The declared tier's half of the invariant above: it carries the declared primitives and leaves every
     * declared reference to whichever loader resolves it. `isPrimitive()` is the same predicate
     * {@see \Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider} keys off, so the
     * set seeded into storage and the set served from it are the same set by construction.
     *
     * @param array<string, PropertySpecification> $declaredProperties
     *
     * @return list<string>
     */
    private function declaredPrimitiveKeys(array $declaredProperties): array
    {
        $keys = [];

        foreach ($declaredProperties as $key => $property) {
            if ($property->type()->isPrimitive()) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * A component naming no registered type declares nothing rather than failing: the virtual root is
     * exactly that case, since its component has no type definition at all.
     *
     * @return array<string, PropertySpecification>
     */
    private function declaredProperties(string $component): array
    {
        if (!$this->typeRegistry->has($component)) {
            return [];
        }

        return $this->typeRegistry->get($component)->properties();
    }
}
