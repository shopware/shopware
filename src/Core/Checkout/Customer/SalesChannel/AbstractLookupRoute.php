<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route is used to validate, if a customer exists
 */
#[Package('checkout')]
abstract class AbstractLookupRoute
{
    abstract public function getDecorated(): AbstractLookupRoute;

    abstract public function lookup(RequestDataBag $data, SalesChannelContext $context): SuccessResponse;
}
