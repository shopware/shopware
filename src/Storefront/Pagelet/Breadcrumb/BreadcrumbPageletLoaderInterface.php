<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Breadcrumb;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
interface BreadcrumbPageletLoaderInterface
{
    public function load(Request $request, SalesChannelContext $context): BreadcrumbPagelet;
}
