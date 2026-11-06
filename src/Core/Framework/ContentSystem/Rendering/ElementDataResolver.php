<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentityFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Runs one {@see StoredElement}'s data requirements and hands back what they resolved to, keyed by
 * requirement key. It is the data-loading half of the render step and nothing more: it goes through the
 * loader provider and the input resolver, applies the cache-tag rule below, and returns the values rather
 * than writing them into the element, so nothing it touches is mutable and the caller decides what the
 * values become. {@see RenderedElementFactory::create()} is that caller, and takes this map as its
 * `$resolvedLoaderValues`.
 *
 * Every requirement key appears in the returned map — with the loaded data when the loader found some, and
 * with `null` when it did not. A {@see ContentDataLoaderResult::notFound()} still writes its key, and that
 * is the point: on the rendered side a present `null` means "a loader ran and found nothing" while an
 * absent key means the property never existed. The factory reads this map with `array_key_exists`, so a key
 * omitted here is a property that never renders and a key held here at `null` is one that renders as null.
 *
 * Cacheability propagates as follows: a result that is not cache-aware disables the
 * {@see RenderingCacheContext} and contributes no tags, and any other result adds its tags. `disable()` is
 * irreversible by design, so a later cache-aware requirement adds its tags without lifting the disable.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ElementDataResolver
{
    public function __construct(
        private DataLoaderProvider $dataLoaderProvider,
        private LoaderInputResolver $inputResolver,
        private LoaderValueIdentityFactory $identityFactory,
    ) {
    }

    /**
     * The ordinary case: an element runs its OWN data requirements, and is also the element whose stored
     * properties the loader inputs are dereferenced against.
     *
     * @return array<string, ResolvedLoaderValue> every requirement's resolved value, keyed by requirement key
     */
    public function resolve(
        StoredElement $stored,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
    ): array {
        return $this->resolveRequirements($stored, $stored->dataRequirements, $context, $request, $cacheContext);
    }

    /**
     * The same run with the requirements supplied separately, for the page-level requirements the virtual root
     * carries none of: they belong to the rendering specification, not to any element, while their
     * `propertyReference` inputs still have to dereference against SOME element's stored properties: the
     * wrapper's, which is where the placeholder values live. `$inputSource` is that element and nothing more;
     * its own `dataRequirements` are not consulted, so it cannot load twice.
     *
     * The identity is minted here and nowhere later: the resolved inputs do not outlive this loop, and the
     * value's fingerprint has to be taken from what the LOADER returned rather than from whatever the response
     * finally carries.
     *
     * @param array<string, DataRequirement> $requirements keyed by requirement key
     *
     * @return array<string, ResolvedLoaderValue> every requirement's resolved value, keyed by requirement key
     */
    public function resolveRequirements(
        StoredElement $inputSource,
        array $requirements,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
    ): array {
        if ($requirements === []) {
            return [];
        }

        $properties = $this->unwrapProperties($inputSource->properties());
        $resolved = [];

        foreach ($requirements as $key => $requirement) {
            $loader = $this->dataLoaderProvider->get($requirement->source);

            $inputs = $this->inputResolver->resolve(
                $loader->configSpecification(),
                $requirement->config,
                $properties,
            );

            $result = $loader->load($inputs, $requirement, $context, $request);

            $resolved[$key] = new ResolvedLoaderValue(
                $result->data,
                $this->identityFactory->create($requirement, $inputs, $result->data),
            );

            $this->processCacheTags($result, $cacheContext);
        }

        return $resolved;
    }

    /**
     * {@see LoaderInputResolver::resolve()} dereferences a loader's property-reference keys against raw
     * values and is shared with the live hydration path, so the stored map is unwrapped before it gets
     * there rather than the resolver being taught about wrapped values.
     *
     * @param array<string, StoredValue> $properties
     *
     * @return array<string, mixed>
     */
    private function unwrapProperties(array $properties): array
    {
        return array_map(static fn (StoredValue $value): mixed => $value->jsonSerialize(), $properties);
    }

    private function processCacheTags(ContentDataLoaderResult $result, RenderingCacheContext $cacheContext): void
    {
        if (!$result->isCacheAware()) {
            $cacheContext->disable();

            return;
        }

        $cacheContext->addTags($result->getCacheTags());
    }
}
