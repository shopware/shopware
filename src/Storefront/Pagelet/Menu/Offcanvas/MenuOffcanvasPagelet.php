<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Menu\Offcanvas;

use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Pagelet\NavigationPagelet;

#[Package('storefront')]
class MenuOffcanvasPagelet extends NavigationPagelet
{
    public function setNavigation(Tree $navigation): void
    {
        $this->navigation = $navigation;
    }
}
