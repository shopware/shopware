<?php declare(strict_types=1);

namespace Shopware\Core\Content\CancellationRequest\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('after-sales')]
abstract class AbstractCancellationRequestRoute
{
    abstract public function getDecorated(): CancellationRequestRoute;

    abstract public function request(RequestDataBag $dataBag, SalesChannelContext $context): CancellationRequestRouteResponse;
}
