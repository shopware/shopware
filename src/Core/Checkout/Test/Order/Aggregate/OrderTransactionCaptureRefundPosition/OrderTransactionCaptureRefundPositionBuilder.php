<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\Aggregate\OrderTransactionCaptureRefundPosition;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Test\TestBuilderTrait;

/**
 * @internal
 */
class OrderTransactionCaptureRefundPositionBuilder
{
    use TestBuilderTrait;

    protected string $id;

    protected string $refundId;

    protected ?string $orderLineItemId = null;

    protected CalculatedPrice $amount;

    protected ?string $externalReference = null;

    protected ?string $reason = null;

    public function __construct(
        IdsCollection $ids,
        string $key,
        string $refundId,
        float $amount = 420.69,
        ?string $externalReference = null,
        ?string $reason = null,
        ?string $orderLineItemId = null
    ) {
        $this->id = $ids->get($key);
        $this->ids = $ids;
        $this->refundId = $refundId;
        $this->externalReference = $externalReference;
        $this->reason = $reason;
        $this->orderLineItemId = $orderLineItemId;

        $this->amount($amount);

        if (!$orderLineItemId) {
            $this->add('orderLineItem', (new ProductBuilder($this->ids, '10000'))
                ->add('identifier', $this->ids->get('order_line_item'))
                ->add('quantity', 1)
                ->add('label', 'foo')
                ->add('price', new CalculatedPrice(
                    420.69,
                    420.69,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ))
                ->build());
        }
    }

    public function amount(float $amount): self
    {
        $this->amount = new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection());

        return $this;
    }
}
