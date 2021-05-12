<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

/**
 * @internal only for use by the app-system
 */
interface PaymentPayloadInterface extends \JsonSerializable
{
    public function setSource(Source $source): void;

    public function getOrderTransaction(): OrderTransactionEntity;
}
