<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
interface FooterPageletLoaderInterface
{
    public function load(Request $request, SalesChannelContext $salesChannelContext): FooterPagelet;
}
