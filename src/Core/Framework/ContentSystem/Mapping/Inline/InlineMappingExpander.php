<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Inline;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * Replaces every `{{map:path}}` token in a forest's `inlineMappable` string properties with the text it resolves to,
 * producing the forest the mint then reads.
 *
 * WHY THIS IS A STORED-TREE REWRITE rather than a step inside `Rendering/RenderedElementFactory`. The factory mints
 * a property from four tiers in a fixed lowest-first order, and provenance is recorded per tier. An inline token
 * lives inside a DeclaredAuthored value and does not change which tier wins — the property is still authored, still
 * beaten by anything above it. Interpolating here keeps that true by construction: the factory receives an ordinary
 * authored string and needs to know nothing about mapping. Threading resolution into the factory instead would mean
 * a fifth concern in the one method whose ordering is, as its own docblock puts it, load-bearing twice.
 *
 * The consequence to be aware of is that the value the mint records as DeclaredAuthored is the EXPANDED text, not
 * what the author typed. That is the right answer for a renderer — a storefront reads resolved output — and the
 * Administration never sees this forest, because it reads stored elements through the codec instead.
 *
 * SHAPE RULE, borrowed verbatim from `Layout/Scaffolding/StoredTreePreparer::resolvePlaceholders()`: top-level
 * string properties only, containers handed on untouched even when they hold string leaves. A token is a property of
 * the authored value at a declared, `inlineMappable`-flagged key; reaching into a list or map would expand text that
 * no declaration ever offered to expand, and no property specification exists to have flagged it.
 *
 * @internal
 */
#[Package('framework')]
final readonly class InlineMappingExpander
{
    public function __construct(
        private AbstractContentSystemElementTypeRegistry $typeRegistry,
        private InlineMappingTokenParser $parser,
        private InlineMappingInterpolator $interpolator,
    ) {
    }

    /**
     * @param list<StoredElement> $forest
     * @param array<string, mixed> $ambient root-ambient values keyed by page-level data requirement key
     * @param string|null $rootSource the layout's root source, from `RenderingSpecification::$rootSource`
     *
     * @return list<StoredElement> the same forest when nothing held a token
     */
    public function expand(array $forest, array $ambient, ?string $rootSource): array
    {
        // No root source means no catalogue, and every token would resolve to nothing and stay verbatim. Skipping
        // outright rather than walking to the same answer keeps a context-free layout's render free of this step.
        if ($rootSource === null) {
            return $forest;
        }

        return array_map(
            fn (StoredElement $element): StoredElement => $this->expandElement($element, $ambient, $rootSource),
            $forest
        );
    }

    /**
     * @param array<string, mixed> $ambient
     */
    private function expandElement(StoredElement $element, array $ambient, string $rootSource): StoredElement
    {
        $properties = $element->properties();
        $rewritten = $properties;

        foreach ($properties as $key => $value) {
            if (!$value->isString()) {
                continue;
            }

            $text = $value->asString();

            // The cheap check first, because the overwhelming majority of string properties in a forest hold no
            // token and this spares them both the catalogue lookup and the regex.
            if (!$this->parser->containsToken($text) || !$this->isInlineMappable($element->component, $key)) {
                continue;
            }

            $rewritten[$key] = StoredValue::ofString(
                $this->parser->replace(
                    $text,
                    fn (string $path): ?string => $this->interpolator->interpolate(
                        $path,
                        $ambient,
                        $rootSource,
                        $element->id
                    )
                )
            );
        }

        $slots = [];
        foreach ($element->slots as $slotName => $children) {
            $slots[$slotName] = array_map(
                fn (StoredElement $child): StoredElement => $this->expandElement($child, $ambient, $rootSource),
                $children
            );
        }

        return $element->withProperties($rewritten)->withSlots($slots);
    }

    /**
     * An unregistered component or an undeclared key answers false, which leaves the token verbatim. Both mean the
     * text cannot be shown to have opted in, and expanding on the strength of a token's own presence would make the
     * `inlineMappable` flag advisory rather than the gate it is.
     */
    private function isInlineMappable(string $component, string $propertyKey): bool
    {
        if (!$this->typeRegistry->has($component)) {
            return false;
        }

        $property = $this->typeRegistry->get($component)->properties()[$propertyKey] ?? null;

        return $property?->inlineMappable() ?? false;
    }
}
