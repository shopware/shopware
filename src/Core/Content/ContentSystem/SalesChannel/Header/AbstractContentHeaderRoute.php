<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Header;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns header layout metadata with fully hydrated element trees.
 */
#[Package('discovery')]
abstract class AbstractContentHeaderRoute
{
    abstract public function getDecorated(): AbstractContentHeaderRoute;

    abstract public function load(Request $request, SalesChannelContext $context): ContentHeaderRouteResponse;
}
