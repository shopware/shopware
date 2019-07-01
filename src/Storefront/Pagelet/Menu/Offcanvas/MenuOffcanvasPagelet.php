<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Menu\Offcanvas;

use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Struct\Struct;

class MenuOffcanvasPagelet extends Struct
{
    /**
     * @var Tree
     */
    protected $navigation;

    public function __construct(Tree $navigation)
    {
        $this->setNavigation($navigation);
    }

    public function getNavigation(): Tree
    {
        return $this->navigation;
    }

    public function setNavigation(Tree $navigation): void
    {
        $this->navigation = $navigation;
    }
}
