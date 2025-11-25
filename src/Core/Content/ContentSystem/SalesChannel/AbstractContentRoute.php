<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns layout metadata with fully hydrated element trees.
 */
#[Package('discovery')]
abstract class AbstractContentRoute
{
    abstract public function getDecorated(): AbstractContentRoute;

    abstract public function load(string $path, Request $request, SalesChannelContext $context): ContentRouteResponse;
}
