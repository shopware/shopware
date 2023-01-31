<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation\Error;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
interface ErrorPageLoaderInterface
{
    public function load(string $cmsErrorLayoutId, Request $request, SalesChannelContext $context): ErrorPage;
}
