<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Refinery;

use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @final
 */
#[Package('discovery')]
class RefinedLayoutBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly LayoutRefinery $refinery
    ) {
    }

    /**
     * Refines pre-loaded layout entity.
     */
    public function refine(
        ContentLayoutEntity $layoutEntity,
        RenderingSpecification $specification,
        SalesChannelContext $salesChannelContext
    ): RefinedLayout {
        $contentLayouts = $layoutEntity->getLayout();

        return new RefinedLayout($layoutEntity, $this->refineElements($contentLayouts, $specification, $salesChannelContext));
    }

    /**
     * @param array<\Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement> $contentLayouts
     *
     * @return \Generator<\Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement>
     */
    private function refineElements(
        array $contentLayouts,
        RenderingSpecification $specification,
        SalesChannelContext $salesChannelContext
    ): \Generator {
        foreach ($contentLayouts as $element) {
            yield $this->refinery->refine($element, $specification, $salesChannelContext);
        }
    }
}
