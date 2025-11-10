<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Extension point for layout scaffolding.
 *
 * Scaffolders modify layouts before and after processing with symmetric operations.
 * Execute in priority order during scaffold(), reverse order during dismantle().
 *
 * @internal
 */
#[Package('discovery')]
interface LayoutScaffolderInterface
{
    /**
     * Execution priority.
     *
     * Higher priority executes FIRST during scaffold() and LAST during dismantle().
     *
     * Example priorities:
     * - 100: VirtualRootScaffolder (outermost layer)
     * - 50: Custom intermediate scaffolders
     * - 0: Custom innermost scaffolders
     *
     * @return int Priority (0-1000, higher = outer layer)
     */
    public static function getPriority(): int;

    /**
     * Scaffolds layout before refinement and hydration.
     *
     * Called in priority order: highest → lowest.
     * May modify root elements, inject wrappers, or alter layout metadata.
     *
     * @param ContentLayoutEntity $layout Layout to scaffold
     * @param RenderingSpecification $specification Rendering configuration
     * @param SalesChannelContext $context Sales channel context
     *
     * @return ContentLayoutEntity Scaffolded layout
     */
    public function scaffold(
        ContentLayoutEntity $layout,
        RenderingSpecification $specification,
        SalesChannelContext $context
    ): ContentLayoutEntity;

    /**
     * Dismantles scaffolding after hydration.
     *
     * Called in REVERSE priority order: lowest → highest.
     * Must reverse any modifications made during scaffold().
     *
     * @param ContentLayoutEntity $layout Hydrated layout to dismantle
     * @param RenderingSpecification $specification Rendering configuration
     * @param SalesChannelContext $context Sales channel context
     *
     * @return ContentLayoutEntity Dismantled layout
     */
    public function dismantle(
        ContentLayoutEntity $layout,
        RenderingSpecification $specification,
        SalesChannelContext $context
    ): ContentLayoutEntity;
}
