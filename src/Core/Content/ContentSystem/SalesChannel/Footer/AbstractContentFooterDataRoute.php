<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Footer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns footer data and assignments without skeleton structure.
 */
#[Package('discovery')]
abstract class AbstractContentFooterDataRoute
{
    abstract public function getDecorated(): AbstractContentFooterDataRoute;

    abstract public function load(Request $request, SalesChannelContext $context): ContentFooterDataRouteResponse;
}
