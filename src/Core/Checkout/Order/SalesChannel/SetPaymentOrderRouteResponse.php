<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SuccessResponse;

#[Package('customer-order')]
class SetPaymentOrderRouteResponse extends SuccessResponse
{
}
