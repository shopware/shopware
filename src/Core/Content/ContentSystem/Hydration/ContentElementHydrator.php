<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DataContextResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads data and resolves context for content elements.
 *
 * @internal
 */
#[Package('discovery')]
class ContentElementHydrator
{
    public function __construct(
        private readonly DataLoaderProvider $dataLoaderProvider,
        private readonly DataContextResolver $contextResolver
    ) {
    }

    /**
     * @param iterable<ContentElement> $elements
     *
     * @return \Generator<ContentElement>
     */
    public function hydrate(iterable $elements, SalesChannelContext $context, Request $request): \Generator
    {
        // Phase 1: Data loading (two-phase constraint - must complete before context resolution)
        $loadedElements = [];
        foreach ($elements as $element) {
            $this->hydrateElement($element, $context, $request);
            $loadedElements[] = $element;
        }

        // Phase 2: Context resolution (providers may expose loaded data as context)
        foreach ($loadedElements as $element) {
            $this->contextResolver->resolve($element);
            yield $element;
        }
    }

    private function hydrateElement(ContentElement $element, SalesChannelContext $context, Request $request): void
    {
        if ($element->requiresData()) {
            $dataRequirements = $element->getDataRequirements();

            foreach ($dataRequirements as $key => $requirement) {
                $loader = $this->dataLoaderProvider->get($requirement->source);
                $data = $loader->load($element, $requirement, $context, $request);

                if ($data !== null) {
                    $element->setProperty($key, $data);
                }
            }
        }

        foreach ($element->allSlotElements() as $child) {
            $this->hydrateElement($child, $context, $request);
        }
    }
}
