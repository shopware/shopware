<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Refinery;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\Struct\ResolvedData;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Refines content layouts in priority order (single sequential pass).
 *
 * Priority: PlaceholderResolutionRefiner=0 (last), extensions>0.
 * Refiners adding placeholders must resolve to final values (no recursion).
 *
 * @internal
 */
#[Package('discovery')]
interface LayoutRefinerInterface
{
    /**
     * Refines a content layout.
     *
     * @param ContentElement $layout The layout to refine
     * @param ResolvedData $resolvedData Entity IDs and parameters from routing
     * @param SalesChannelContext $context Sales channel context
     *
     * @return ContentElement The refined layout
     */
    public function refine(
        ContentElement $layout,
        ResolvedData $resolvedData,
        SalesChannelContext $context
    ): ContentElement;
}
