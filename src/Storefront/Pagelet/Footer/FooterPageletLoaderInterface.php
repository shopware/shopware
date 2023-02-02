<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Decoratable()
 */
interface FooterPageletLoaderInterface
{
    public function load(Request $request, SalesChannelContext $salesChannelContext): FooterPagelet;
}
