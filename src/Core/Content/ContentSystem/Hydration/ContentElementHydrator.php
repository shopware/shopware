<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration;

use Shopware\Core\Content\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DataContextResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ContentElementHydrator
{
    public function __construct(
        private readonly DataLoaderProvider $dataLoaderProvider,
        private readonly DataContextResolver $contextResolver,
    ) {
    }

    /**
     * @param iterable<ContentElement> $elements
     *
     * @return \Generator<ContentElement>
     */
    public function hydrate(
        iterable $elements,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
    ): \Generator {
        $loadedElements = [];
        foreach ($elements as $element) {
            $this->hydrateElement($element, $context, $request, $cacheContext);
            $loadedElements[] = $element;
        }

        foreach ($loadedElements as $element) {
            $this->contextResolver->resolve($element);
            yield $element;
        }
    }

    private function hydrateElement(
        ContentElement $element,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
    ): void {
        if ($element->requiresData()) {
            $dataRequirements = $element->getDataRequirements();

            foreach ($dataRequirements as $key => $requirement) {
                $loader = $this->dataLoaderProvider->get($requirement->source);
                $result = $loader->load($element, $requirement, $context, $request);

                if ($result->hasData()) {
                    $element->setProperty($key, $result->data);
                }

                $this->processCacheTags($result, $cacheContext);
            }
        }

        foreach ($element->allSlotElements() as $child) {
            $this->hydrateElement($child, $context, $request, $cacheContext);
        }
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
