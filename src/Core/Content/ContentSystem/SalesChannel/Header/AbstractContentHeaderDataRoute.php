<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Header;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns header data and assignments without skeleton structure.
 */
#[Package('discovery')]
abstract class AbstractContentHeaderDataRoute
{
    abstract public function getDecorated(): AbstractContentHeaderDataRoute;

    abstract public function load(Request $request, SalesChannelContext $context): ContentHeaderDataRouteResponse;
}
