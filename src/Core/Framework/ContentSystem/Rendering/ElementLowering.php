<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\MappingRuntimeDiagnosticCollector;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext\StorefrontContextLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingExpander;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingMetadata;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\StorefrontContextMappingCandidateProvider;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Turns one stored forest into the rendered forest it serves as. The three render layers underneath it each
 * answer one question about one thing — {@see ElementDataResolver} runs a single element's data requirements,
 * {@see ContextDeliveryResolver} answers what a whole forest's elements received, {@see RenderedTreeFactory}
 * mints the tree — and this is the one place that owns the step as a whole. The forest-wide data walk lives
 * here because no other layer is in a position to do it: the data resolver sees one element at a time, and
 * the delivery resolver takes the loader values already collected.
 *
 * Ordering is the substance of this class. In FULL mode loading completes over the WHOLE forest before any
 * distribution starts, because a provider may hand a loaded value on to a child: process an element's
 * distribution before a later element has loaded, and the value that element was going to provide is not
 * there yet. That is why {@see ContextDeliveryResolver::resolve()} takes the loader values as an argument
 * rather than resolving them itself, and this class is what fills that argument.
 *
 * THE PAGE-LEVEL DATA IS RESOLVED HERE TOO, and separately. It belongs to the rendering specification rather
 * than to any element, so it arrives as an argument beside the forest and is run against the
 * {@see VirtualRootWrapper} element, whose placeholder values are what its loaders' `propertyReference` inputs
 * dereference against. Only requirements referenced by a root-scoped context consumer, a stored mapping's source
 * path, or an inline mapping token in an opted-in string property are resolved: an unreferenced requirement never
 * loads, so it runs no query and contributes no cache tag. Consumption is judged over the wrapper's pre-prune
 * forest, so a partial render resolves the same set as the full render. The resulting map is handed to
 * {@see ContextDeliveryResolver::resolve()} as the root-ambient context, which is the only route
 * by which it reaches an element. It is an explicit input on that call rather than something read off the tree,
 * so the partial prune cannot take root context with it. The same map is also filed into the forest's loader
 * values under the wrapper's own id, which on a partial render that pruned the wrapper away addresses no
 * element of that forest and is read by nothing.
 *
 * The data walk is pre-order and descends slot by slot: an element loads before the elements under it, and
 * each slot's children load in declaration order. What that order buys is narrower than it looks:
 * {@see RenderingCacheContext::disable()} is an irreversible flag and {@see RenderingCacheContext::addTags()}
 * dedupes, so neither the disabled state nor the tag SET depends on it — only the tag list's first-occurrence
 * order does, along with whatever order a loader's own side effects are sensitive to. Those two are what the
 * order is fixed for; nothing else in the module's output depends on it.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ElementLowering
{
    public function __construct(
        private ElementDataResolver $dataResolver,
        private ContextDeliveryResolver $deliveryResolver,
        private RenderedTreeFactory $treeFactory,
        private InlineMappingExpander $inlineMappingExpander,
        private MappingConsumers $mappingConsumers,
        private ContextPathResolver $pathResolver,
    ) {
    }

    /**
     * SKELETON resolves no data and computes no deliveries, and the mint below is handed an empty index and
     * an empty loader-value map. That is not an optimisation: a skeleton is a structural answer, so no loader
     * runs, nothing is contributed to `$cacheContext`, and {@see RenderedTreeFactory} reads neither argument
     * in that mode. The two modes differ in data resolution alone — the traversal that shapes the tree is one
     * code path either way. The ambient resolution below follows that split: a skeleton runs no page-level
     * loader either, and the delivery step it never reaches would have taken an empty ambient map.
     *
     * @param list<StoredElement> $forest roots in order
     * @param list<DataRequirement> $pageDataRequirements the rendering specification's page-level requirements
     * @param StoredElement|null $virtualRoot the wrapper the preparation minted, or null when it did not wrap
     * @param string|null $rootSource the specification's root source, naming the mapping catalogue inline tokens
     *                                resolve against; without one, tokens in opted-in inline properties are removed
     *
     * @throws ContentSystemException when a required consumer's path cannot be resolved
     */
    public function lower(
        array $forest,
        RenderingMode $mode,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
        array $pageDataRequirements,
        ?StoredElement $virtualRoot,
        ?string $rootSource = null,
        ?InlineMappingMetadata $inlineMappings = null,
    ): LoweringResult {
        if ($mode === RenderingMode::SKELETON) {
            return $this->treeFactory->create($forest, new ContextDeliveryIndex(), [], $mode);
        }

        $mappingDiagnostics = new MappingRuntimeDiagnosticCollector();
        $inlineMappings ??= new InlineMappingMetadata();

        $loaderValues = $this->resolveLoaderValues($forest, $context, $request, $cacheContext, $rootSource, $inlineMappings);
        $ambient = [];

        // No wrapper, or no page-level requirement referenced by a root-scoped consumer or mapping: nothing
        // ambient to resolve. The wrapper comes from the pre-prune forest, so consumption is judged over the
        // whole page and a partial render loads the same requirements as the full render.
        if ($virtualRoot !== null) {
            $consumed = $this->consumedRequirements([$virtualRoot], $pageDataRequirements, $inlineMappings);

            if ($consumed !== []) {
                $ambient = $this->dataResolver->resolveRequirements(
                    $virtualRoot,
                    $this->indexByRequirementKey($consumed),
                    $context,
                    $request,
                    $cacheContext,
                );

                // Filed under the wrapper's id because that is the element these values were resolved against,
                // which keeps the map's "loader values by element id" contract true. The wrapper carries no data
                // requirements of its own, so this entry adds no rendered property to it and nothing loads twice.
                $loaderValues[$virtualRoot->id] = $ambient;
            }
        }

        $plainAmbient = array_map(static fn (ResolvedLoaderValue $resolved): mixed => $resolved->value, $ambient);

        // Context distribution is about dataflow, not about where a value came from, so it sees the plain
        // values: a provider hands a child what it renders, and the identity beside it means nothing there.
        $plainLoaderValues = $this->plainValues($loaderValues);
        $deliveries = $this->deliveryResolver->resolve(
            $forest,
            $plainLoaderValues,
            $plainAmbient,
            $context,
            $request,
            $cacheContext,
            $rootSource,
            $mappingDiagnostics,
        );

        // Inline mapping expansion sits between delivery and the mint, and that is the only window it fits in: it
        // needs the ambient values, which the page-level resolution above produces, and it must finish before the
        // mint reads a property, because what it rewrites is an authored property's text. Delivery is unaffected
        // either way — it reads context wiring and loader values, never an authored string — so the deliveries
        // computed from the pre-expansion forest stay valid against the expanded one: expansion changes property
        // VALUES and no element id, and the delivery index is keyed by id.
        $forest = $this->inlineMappingExpander->expand(
            $forest,
            $plainAmbient,
            $rootSource,
            $context,
            $request,
            $cacheContext,
            $plainLoaderValues,
            $mappingDiagnostics,
            $inlineMappings,
        );

        $lowered = $this->treeFactory->create($forest, $deliveries, $loaderValues, $mode);

        return new LoweringResult($lowered->tree, $lowered->provenance, $mappingDiagnostics->violations());
    }

    /**
     * @param list<DataRequirement> $requirements
     *
     * @return array<string, DataRequirement>
     */
    private function indexByRequirementKey(array $requirements): array
    {
        $indexed = [];

        foreach ($requirements as $requirement) {
            $indexed[$requirement->key] = $requirement;
        }

        return $indexed;
    }

    /**
     * @param list<StoredElement> $forest
     * @param list<DataRequirement> $pageDataRequirements
     *
     * @return list<DataRequirement>
     */
    private function consumedRequirements(array $forest, array $pageDataRequirements, InlineMappingMetadata $inlineMappings): array
    {
        $rootConsumerKeys = $this->rootConsumerKeys($forest, $inlineMappings);

        return array_values(array_filter(
            $pageDataRequirements,
            fn (DataRequirement $requirement): bool => $this->isConsumed($requirement, $rootConsumerKeys),
        ));
    }

    /**
     * Matches page requirements against the full set of root-ambient references collected by
     * {@see rootConsumerKeys()}: root-scoped context consumers, stored whole-field mapping paths, and inline
     * mapping token paths. Omitting either mapping form here would skip a requirement needed to resolve it.
     *
     * @param list<string> $rootConsumerKeys
     */
    private function isConsumed(DataRequirement $requirement, array $rootConsumerKeys): bool
    {
        foreach ($rootConsumerKeys as $consumerKey) {
            if ($this->pathResolver->matches($requirement->key, $consumerKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<StoredElement> $elements
     *
     * @return list<string>
     */
    private function rootConsumerKeys(array $elements, InlineMappingMetadata $inlineMappings): array
    {
        $keys = [];

        foreach ($elements as $element) {
            $keys[] = $element->contextDefinitions->getConsumerKeysByScope(ConsumerScope::Root);
            $keys[] = array_values($this->mappingConsumers->mappedPaths($element));
            $keys[] = $this->inlineMappingExpander->mappingPaths($element, $inlineMappings);

            foreach ($element->slots as $children) {
                $keys[] = $this->rootConsumerKeys($children, $inlineMappings);
            }
        }

        return array_values(array_unique(array_merge([], ...$keys)));
    }

    /**
     * @param array<string, array<string, ResolvedLoaderValue>> $loaderValues
     *
     * @return array<string, array<string, mixed>>
     */
    private function plainValues(array $loaderValues): array
    {
        return array_map(
            static fn (array $values): array => array_map(
                static fn (ResolvedLoaderValue $resolved): mixed => $resolved->value,
                $values
            ),
            $loaderValues
        );
    }

    /**
     * An element whose requirements resolved to nothing contributes no entry at all. Both readers of this map
     * take `$loaderValues[$id] ?? []`, so an absent entry and an empty one are the same fact to them, and the
     * map that holds only elements that actually ran a loader is the one that says so. An element whose
     * loader ran and found nothing is a different case and does get an entry: {@see ElementDataResolver}
     * returns the requirement key at `null` there, and that present null is what makes the rendered property
     * exist as null instead of being dropped.
     *
     * @param list<StoredElement> $forest
     *
     * @return array<string, array<string, ResolvedLoaderValue>> element id => requirement key => resolved value
     */
    private function resolveLoaderValues(
        array $forest,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
        ?string $rootSource,
        InlineMappingMetadata $inlineMappings,
    ): array {
        $values = [];

        foreach ($forest as $root) {
            $this->collectLoaderValues($root, $context, $request, $cacheContext, $rootSource, $inlineMappings, $values);
        }

        return $values;
    }

    /**
     * The internal Storefront context requirement is kept only while a whole-field consumer or inline token
     * needs it. Inline text has no map operation to persist such a requirement, so it declares one here for
     * this render. This preserves the normal data-loader cacheability path for both mapping forms.
     *
     * @param array<string, array<string, ResolvedLoaderValue>> $values
     */
    private function collectLoaderValues(
        StoredElement $element,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
        ?string $rootSource,
        InlineMappingMetadata $inlineMappings,
        array &$values,
    ): void {
        $requirements = $element->dataRequirements;
        $usesStorefrontContext = $rootSource !== null && $this->usesStorefrontContext($element, $inlineMappings);
        if ($usesStorefrontContext) {
            $requirements[StorefrontContextLoader::REQUIREMENT_KEY] = StorefrontContextLoader::requirement();
        } else {
            unset($requirements[StorefrontContextLoader::REQUIREMENT_KEY]);
        }

        $resolved = $this->dataResolver->resolveRequirements($element, $requirements, $context, $request, $cacheContext);

        if ($resolved !== []) {
            $values[$element->id] = $resolved;
        }

        foreach ($element->slots as $children) {
            foreach ($children as $child) {
                $this->collectLoaderValues($child, $context, $request, $cacheContext, $rootSource, $inlineMappings, $values);
            }
        }
    }

    private function usesStorefrontContext(StoredElement $element, InlineMappingMetadata $inlineMappings): bool
    {
        foreach ($element->contextDefinitions->getAllConsumers() as $consumer) {
            $source = $consumer->source;
            if ($source !== null && StorefrontContextMappingCandidateProvider::isApprovedSource($source)) {
                return true;
            }
        }

        foreach ($this->inlineMappingExpander->mappingPaths($element, $inlineMappings) as $path) {
            if (StorefrontContextMappingCandidateProvider::isApprovedDisplayName($path)) {
                return true;
            }
        }

        return false;
    }
}
