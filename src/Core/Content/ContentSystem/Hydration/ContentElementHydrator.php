<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DataContextResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Refinery\RefinedLayout;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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

    public function hydrate(RefinedLayout $refinedLayout, SalesChannelContext $context): void
    {
        $this->hydrateElement($refinedLayout->rootElement, $context);
        $this->contextResolver->resolve($refinedLayout->rootElement);
    }

    private function hydrateElement(ContentElement $element, SalesChannelContext $context): void
    {
        if ($element->requiresData()) {
            $dataRequirements = $element->getDataRequirements();

            foreach ($dataRequirements as $key => $requirement) {
                $loader = $this->dataLoaderProvider->get($requirement->source);
                $data = $loader->load($element, $requirement, $context);

                if ($data !== null) {
                    $element->setProperty($key, $data);
                }
            }
        }

        foreach ($element->allSlotElements() as $child) {
            $this->hydrateElement($child, $context);
        }
    }
}
