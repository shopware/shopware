<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Menu\Offcanvas;

use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Pagelet\NavigationPagelet;

#[Package('framework')]
class MenuOffcanvasPagelet extends NavigationPagelet
{
    /**
     * @deprecated tag:v6.7.0 - Will be removed, as it is unused
     */
    public function setNavigation(Tree $navigation): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.7.0.0')
        );
        $this->navigation = $navigation;
    }
}
