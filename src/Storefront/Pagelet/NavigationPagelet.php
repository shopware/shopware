<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet;

use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class NavigationPagelet extends Pagelet
{
    public function __construct(
        protected ?Tree $navigation,
    ) {
    }

    public function getNavigation(): ?Tree
    {
        return $this->navigation;
    }
}
