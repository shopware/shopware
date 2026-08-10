<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\View;

use Shopware\Core\Checkout\DocumentV2\Template\Enum\PaymentMeansCode;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * Precomputed view of an order's payment-means.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class PaymentMeansView
{
    final public const CASH_TECHNICAL_NAME = 'payment_cashpayment';

    final public const INVOICE_TECHNICAL_NAME = 'payment_invoicepayment';

    final public const PREPAYMENT_TECHNICAL_NAME = 'payment_prepayment';

    public function __construct(
        public PaymentMeansCode $typeCode,
        public string $information,
        public ?string $payeeIban,
        public ?string $payeeBic,
    ) {
    }

    public static function fromOrder(OrderEntity $order, ?string $bankIban, ?string $bankBic): ?self
    {
        $transaction = Feature::isActive('v6.8.0.0')
            ? $order->getPrimaryOrderTransaction()
            : $order->getTransactions()?->last();

        $paymentMethod = $transaction?->getPaymentMethod();

        if ($paymentMethod === null) {
            return null;
        }

        return match ($paymentMethod->getTechnicalName()) {
            self::CASH_TECHNICAL_NAME => new self(
                typeCode: PaymentMeansCode::CASH,
                information: $paymentMethod->getName() ?? '',
                payeeIban: null,
                payeeBic: null,
            ),
            self::INVOICE_TECHNICAL_NAME, self::PREPAYMENT_TECHNICAL_NAME => new self(
                typeCode: PaymentMeansCode::CREDIT_TRANSFER,
                information: $paymentMethod->getName() ?? '',
                payeeIban: $bankIban,
                payeeBic: $bankBic,
            ),
            default => null,
        };
    }
}
