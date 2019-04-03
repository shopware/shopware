<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface PageLoaderInterface
{
    public function load(InternalRequest $request, SalesChannelContext $context);
}
