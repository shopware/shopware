<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.7.0 - Will be removed use NavigationRoute instead
 */
#[Package('inventory')]
interface NavigationLoaderInterface
{
    /**
     * Returns the first two levels of the category tree, as well as all parents of the active category
     * and the active categories first level of children.
     * The provided active id will be marked as selected
     */
    public function load(string $activeId, SalesChannelContext $context, string $rootId, int $depth = 2): Tree;
}
