<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Refinery\Refiner;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Refinery\LayoutRefinerInterface;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\Struct\ResolvedData;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves {{variable}} placeholders in single pass (priority 0). Recursive resolution not supported.
 *
 * @internal
 */
#[Package('discovery')]
class PlaceholderResolutionRefiner implements LayoutRefinerInterface
{
    public function refine(
        ContentElement $layout,
        ResolvedData $resolvedData,
        SalesChannelContext $context
    ): ContentElement {
        $layout->replacePlaceholders($resolvedData);

        return $layout;
    }
}
