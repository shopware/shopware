<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Struct\Struct;

class FooterPagelet extends Struct
{
    /**
     * @var Tree|null
     */
    protected $navigation;

    public function __construct(?Tree $navigation)
    {
        $this->navigation = $navigation;
    }

    public function getNavigation(): ?Tree
    {
        return $this->navigation;
    }
}
