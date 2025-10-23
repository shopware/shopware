<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Refinery;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Orchestrates layout refinement through sequential refiners.
 *
 * IMPORTANT: Single-pass only. Recursive placeholder resolution not supported.
 * Extension refiners adding placeholders must resolve to final values.
 *
 * @internal
 */
#[Package('discovery')]
class LayoutRefinery
{
    /**
     * @param iterable<LayoutRefinerInterface> $refiners
     */
    public function __construct(
        private readonly iterable $refiners
    ) {
    }

    public function refine(
        ContentElement $layout,
        RenderingSpecification $specification,
        SalesChannelContext $salesChannelContext
    ): ContentElement {
        foreach ($this->refiners as $refiner) {
            $layout = $refiner->refine($layout, $specification, $salesChannelContext);
        }

        return $layout;
    }
}
