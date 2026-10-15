<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Inline;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

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
 * what the author typed. Storefront rendering always removes unresolved tokens; the layout-free Administration
 * preview opts into preserving them so authors can see stale mappings alongside diagnostics. The editing surface
 * still reads stored elements through the codec.
 *
 * SHAPE RULE, borrowed verbatim from `Layout/Scaffolding/StoredTreePreparer::resolvePlaceholders()`: top-level
 * string properties only for interpolation. A token is resolved only at a declared, `inlineMappable`-flagged key;
 * containers and other unflagged properties are never interpolated. Storefront output still scrubs valid mapping
 * tokens from their string leaves, so no unresolved mapping syntax reaches the page.
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
     * @param array<string, array<string, mixed>> $loaderValues values keyed by element id, then requirement key
     * @param string|null $rootSource the layout's root source, from `RenderingSpecification::$rootSource`
     *
     * @return list<StoredElement> the same forest when nothing held a token
     */
    public function expand(
        array $forest,
        array $ambient,
        ?string $rootSource,
        ?SalesChannelContext $salesChannelContext = null,
        ?Request $request = null,
        ?RenderingCacheContext $cacheContext = null,
        array $loaderValues = [],
        bool $preserveUncataloguedTokens = false,
    ): array {
        // Previews retain unknown mapping tokens so the Administration can show them alongside diagnostics.
        // Storefront renders always erase a well-formed token that cannot be resolved, including without a catalogue.
        if ($rootSource === null && $preserveUncataloguedTokens) {
            return $forest;
        }

        return array_map(
            fn (StoredElement $element): StoredElement => $this->expandElement($element, $ambient, $rootSource, $salesChannelContext, $request, $cacheContext, $loaderValues, $preserveUncataloguedTokens),
            $forest
        );
    }

    /**
     * The root paths referenced by inline mapping tokens on this element, if its declared string properties opt in.
     *
     * @return list<string>
     */
    public function mappingPaths(StoredElement $element): array
    {
        $paths = [];

        foreach ($element->properties() as $key => $value) {
            if (!$value->isString()) {
                continue;
            }

            $text = $value->asString();

            if (!$this->parser->containsToken($text) || !$this->isInlineMappable($element->component, $key)) {
                continue;
            }

            foreach ($this->parser->parse($text) as $token) {
                $paths[] = $token->path;
            }
        }

        return $paths;
    }

    /**
     * @param array<string, mixed> $ambient
     * @param array<string, array<string, mixed>> $loaderValues
     */
    private function expandElement(
        StoredElement $element,
        array $ambient,
        ?string $rootSource,
        ?SalesChannelContext $salesChannelContext,
        ?Request $request,
        ?RenderingCacheContext $cacheContext,
        array $loaderValues,
        bool $preserveUncataloguedTokens,
    ): StoredElement {
        $properties = $element->properties();
        $rewritten = $properties;

        foreach ($properties as $key => $value) {
            if (!$value->isString()) {
                if (!$preserveUncataloguedTokens) {
                    $rewritten[$key] = $this->removeMappingTokens($value);
                }

                continue;
            }

            $text = $value->asString();

            // The cheap check first, because the overwhelming majority of string properties in a forest hold no
            // token and this spares them both the catalogue lookup and the regex.
            if (!$this->parser->containsToken($text)) {
                continue;
            }

            if (!$this->isInlineMappable($element->component, $key)) {
                if (!$preserveUncataloguedTokens) {
                    $rewritten[$key] = StoredValue::ofString($this->parser->replace($text, static fn (string $path): string => ''));
                }

                continue;
            }

            $rewritten[$key] = StoredValue::ofString(
                $this->parser->replace(
                    $text,
                    function (string $path) use ($ambient, $rootSource, $element, $salesChannelContext, $request, $cacheContext, $loaderValues, $preserveUncataloguedTokens): ?string {
                        if ($rootSource === null) {
                            return $preserveUncataloguedTokens ? null : '';
                        }

                        return $this->interpolator->interpolate(
                            $path,
                            $ambient,
                            $rootSource,
                            $element->id,
                            $element,
                            $salesChannelContext,
                            $request,
                            $cacheContext,
                            $loaderValues[$element->id] ?? []
                        ) ?? ($preserveUncataloguedTokens ? null : '');
                    }
                )
            );
        }

        $slots = [];
        foreach ($element->slots as $slotName => $children) {
            $slots[$slotName] = array_map(
                fn (StoredElement $child): StoredElement => $this->expandElement($child, $ambient, $rootSource, $salesChannelContext, $request, $cacheContext, $loaderValues, $preserveUncataloguedTokens),
                $children
            );
        }

        return $element->withProperties($rewritten)->withSlots($slots);
    }

    private function removeMappingTokens(StoredValue $value): StoredValue
    {
        if ($value->isString()) {
            $text = $value->asString();

            if (!$this->parser->containsToken($text)) {
                return $value;
            }

            return StoredValue::ofString($this->parser->replace($text, static fn (string $path): string => ''));
        }

        if ($value->isList()) {
            return StoredValue::ofList(array_map($this->removeMappingTokens(...), $value->asList()));
        }

        if ($value->isMap()) {
            return StoredValue::ofMap(array_map($this->removeMappingTokens(...), $value->asMap()));
        }

        return $value;
    }

    /**
     * An unregistered component or an undeclared key answers false. Such tokens are never interpolated; storefront
     * rendering removes them, while preview keeps them visible for diagnosis.
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
