<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Refinery\Refiner;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Refinery\LayoutRefinerInterface;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves {{variable}} placeholders (priority 0). Single-pass only, no recursion.
 *
 * @internal
 */
#[Package('discovery')]
class PlaceholderResolutionRefiner implements LayoutRefinerInterface
{
    public function refine(
        ContentElement $layout,
        RenderingSpecification $specification,
        SalesChannelContext $salesChannelContext
    ): ContentElement {
        $layout->replacePlaceholders($specification);

        return $layout;
    }
}
