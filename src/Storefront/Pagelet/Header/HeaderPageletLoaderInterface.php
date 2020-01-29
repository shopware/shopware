<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface HeaderPageletLoaderInterface
{
    public function load(Request $request, SalesChannelContext $salesChannelContext): HeaderPagelet;
}
