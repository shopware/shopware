<?php declare(strict_types=1);

namespace Shopware\Core\Content\RevocationRequest\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('after-sales')]
abstract class AbstractRevocationRequestRoute
{
    abstract public function getDecorated(): AbstractRevocationRequestRoute;

    abstract public function request(RequestDataBag $dataBag, SalesChannelContext $context): RevocationRequestRouteResponse;
}
