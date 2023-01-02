<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Content\Category\Tree\Tree;

/**
 * @package storefront
 */
#[Package('storefront')]
abstract class NavigationPagelet extends Pagelet
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
