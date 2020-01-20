<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface NavigationLoaderInterface
{
    /**
     * Returns the first two levels of the category tree, as well as all parents of the active category
     * and the active categories first level of children.
     * The provided active id will be marked as selected
     */
    public function load(string $activeId, SalesChannelContext $context, string $rootId, int $depth = 2): Tree;

    /**
     * Returns the category tree level for the provided category id.
     */
    public function loadLevel(string $categoryId, SalesChannelContext $context): Tree;
}
