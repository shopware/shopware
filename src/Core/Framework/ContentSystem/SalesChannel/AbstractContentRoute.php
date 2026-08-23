<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns content layout data in the configured output format.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentRoute
{
    abstract public function getDecorated(): AbstractContentRoute;

    abstract public function load(string $path, Request $request, SalesChannelContext $context): AbstractContentRouteResponse;
}
