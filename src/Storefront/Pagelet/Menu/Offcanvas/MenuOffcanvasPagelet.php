<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Menu\Offcanvas;

use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Storefront\Pagelet\NavigationPagelet;

/**
 * @package storefront
 */
class MenuOffcanvasPagelet extends NavigationPagelet
{
    public function setNavigation(Tree $navigation): void
    {
        $this->navigation = $navigation;
    }
}
