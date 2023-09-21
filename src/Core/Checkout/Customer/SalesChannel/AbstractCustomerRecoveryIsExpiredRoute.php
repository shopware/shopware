<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is used get the CustomerRecoveryIsExpiredResponse entry for a given hash
 * The required parameter is: "hash"
 */
#[Package('checkout')]
abstract class AbstractCustomerRecoveryIsExpiredRoute
{
    abstract public function getDecorated(): AbstractCustomerRecoveryIsExpiredRoute;

    abstract public function load(RequestDataBag $data, SalesChannelContext $context): CustomerRecoveryIsExpiredResponse;
}
