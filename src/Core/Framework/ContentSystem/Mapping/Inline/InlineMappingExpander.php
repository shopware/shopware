<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Inline;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\MappingRuntimeDiagnosticCollector;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
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
 * what the author typed. A resolved token is replaced; an unresolved token is removed while surrounding authored
 * text is retained, so a token in a supported inline-mappable property never reaches the Storefront. Diagnostics
 * report why it did not resolve, and the editing surface reads the original stored elements through the codec.
 *
 * SHAPE RULE, borrowed verbatim from `Layout/Scaffolding/StoredTreePreparer::resolvePlaceholders()`: top-level
 * string properties only for interpolation. A token is resolved only at a declared, `inlineMappable`-flagged key;
 * containers and other unflagged properties are left as authored text.
 *
 * @internal
 */
#[Package('framework')]
final readonly class InlineMappingExpander
{
    public function __construct(
        private StoredMappingInspector $mappingInspector,
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
        ?MappingRuntimeDiagnosticCollector $mappingDiagnostics = null,
        ?InlineMappingMetadata $inlineMappings = null,
    ): array {
        $inlineMappings ??= new InlineMappingMetadata();
        $budget = new InlineMappingExpansionBudget();

        return array_map(
            fn (StoredElement $element): StoredElement => $this->expandElement($element, $ambient, $rootSource, $salesChannelContext, $request, $cacheContext, $loaderValues, $mappingDiagnostics, $inlineMappings, $budget),
            $forest
        );
    }

    /**
     * The root paths referenced by inline mapping tokens on this element, if its declared string properties opt in.
     *
     * @return list<string>
     */
    public function mappingPaths(StoredElement $element, InlineMappingMetadata $inlineMappings): array
    {
        $paths = [];

        foreach ($element->properties() as $key => $value) {
            if (!$value->isString()) {
                continue;
            }

            $propertyKey = (string) $key;
            if (!$this->mappingInspector->isInlineMappable($element->component, $propertyKey)) {
                continue;
            }

            foreach ($inlineMappings->tokensFor($element->id, $propertyKey) as $token) {
                if (!$token->insideMarkup && !$token->malformed) {
                    $paths[] = $token->path;
                }
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
        ?MappingRuntimeDiagnosticCollector $mappingDiagnostics,
        InlineMappingMetadata $inlineMappings,
        InlineMappingExpansionBudget $budget,
    ): StoredElement {
        $properties = $element->properties();
        $rewritten = $properties;

        foreach ($properties as $key => $value) {
            if (!$value->isString()) {
                continue;
            }

            $text = $value->asString();
            $propertyKey = (string) $key;
            $tokens = $inlineMappings->tokensFor($element->id, $propertyKey);

            if ($tokens === [] || !$this->mappingInspector->isInlineMappable($element->component, $propertyKey)) {
                continue;
            }

            try {
                $budget->beginProperty();
                $expanded = $this->parser->replaceTokens(
                    $text,
                    $tokens,
                    fn (string $path): string => $rootSource === null
                        ? ''
                        : ($this->interpolator->interpolate(
                            $path,
                            $ambient,
                            $rootSource,
                            $element,
                            $salesChannelContext,
                            $request,
                            $cacheContext,
                            $loaderValues[$element->id] ?? [],
                            $mappingDiagnostics,
                            $propertyKey,
                            $budget,
                        ) ?? ''),
                    $budget,
                );
            } catch (ContentSystemException $exception) {
                if ($exception->getErrorCode() !== ContentSystemException::INLINE_MAPPING_EXPANSION_TOO_LARGE) {
                    throw $exception;
                }

                $mappingDiagnostics?->record($element->id, $propertyKey, '', $exception->getMessage());
                $expanded = '';
            }

            $rewritten[$key] = StoredValue::ofString($expanded);
        }

        $slots = [];
        foreach ($element->slots as $slotName => $children) {
            $slots[$slotName] = array_map(
                fn (StoredElement $child): StoredElement => $this->expandElement($child, $ambient, $rootSource, $salesChannelContext, $request, $cacheContext, $loaderValues, $mappingDiagnostics, $inlineMappings, $budget),
                $children
            );
        }

        return $element->withProperties($rewritten)->withSlots($slots);
    }
}
