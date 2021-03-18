<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractLandingPageRoute
{
    abstract public function getDecorated(): AbstractLandingPageRoute;

    abstract public function load(string $landingPageId, Request $request, SalesChannelContext $context): LandingPageRouteResponse;
}
