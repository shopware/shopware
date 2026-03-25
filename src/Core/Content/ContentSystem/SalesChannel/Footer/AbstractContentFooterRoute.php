<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Footer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns footer layout metadata with fully hydrated element trees.
 */
#[Package('discovery')]
abstract class AbstractContentFooterRoute
{
    abstract public function getDecorated(): AbstractContentFooterRoute;

    abstract public function load(Request $request, SalesChannelContext $context): ContentFooterRouteResponse;
}
