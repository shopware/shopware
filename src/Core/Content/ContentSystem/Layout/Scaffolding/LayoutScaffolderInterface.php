<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Extension point for layout scaffolding with symmetric scaffold/dismantle operations.
 *
 * @internal
 */
#[Package('discovery')]
interface LayoutScaffolderInterface
{
    /**
     * Execution priority (higher = FIRST during scaffold, LAST during dismantle).
     *
     * Examples: 100 (VirtualRootScaffolder), 50 (intermediate), 0 (innermost).
     *
     * @return int Priority (0-1000)
     */
    public static function getPriority(): int;

    /**
     * Scaffolds layout before refinement and hydration (called highest → lowest priority).
     */
    public function scaffold(
        ContentLayoutEntity $layout,
        RenderingSpecification $specification,
        SalesChannelContext $context
    ): ContentLayoutEntity;

    /**
     * Dismantles scaffolding after hydration (called in REVERSE priority: lowest → highest).
     *
     * Must reverse modifications made during scaffold().
     */
    public function dismantle(
        ContentLayoutEntity $layout,
        RenderingSpecification $specification,
        SalesChannelContext $context
    ): ContentLayoutEntity;
}
