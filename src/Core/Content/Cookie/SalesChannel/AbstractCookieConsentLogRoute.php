<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
abstract class AbstractCookieConsentLogRoute
{
    abstract public function getDecorated(): AbstractCookieConsentLogRoute;

    abstract public function log(Request $request, SalesChannelContext $salesChannelContext): NoContentResponse;
}
