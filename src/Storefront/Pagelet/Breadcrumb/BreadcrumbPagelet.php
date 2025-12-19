<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Breadcrumb;

use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Pagelet\Pagelet;

#[Package('framework')]
class BreadcrumbPagelet extends Pagelet
{
    /**
     * @internal
     */
    public function __construct(
        protected BreadcrumbCollection $breadcrumbCollection
    ) {
    }

    public function getBreadcrumbCollection(): ?BreadcrumbCollection
    {
        return $this->breadcrumbCollection;
    }
}
