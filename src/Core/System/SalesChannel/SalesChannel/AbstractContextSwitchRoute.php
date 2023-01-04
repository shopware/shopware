<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('core')]
abstract class AbstractContextSwitchRoute
{
    abstract public function getDecorated(): AbstractContextSwitchRoute;

    abstract public function switchContext(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse;
}
