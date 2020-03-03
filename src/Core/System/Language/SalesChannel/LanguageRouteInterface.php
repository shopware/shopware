<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface LanguageRouteInterface
{
    public function load(Request $request, SalesChannelContext $context): LanguageRouteResponse;
}
