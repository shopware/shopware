<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Menu\Offcanvas;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface MenuOffcanvasPageletLoaderInterface
{
    public function load(Request $request, SalesChannelContext $salesChannelContext): MenuOffcanvasPagelet;
}
