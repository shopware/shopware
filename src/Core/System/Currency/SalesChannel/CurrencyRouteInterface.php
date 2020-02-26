<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface CurrencyRouteInterface
{
    public function load(Request $request, SalesChannelContext $context): CurrencyRouteResponse;
}
