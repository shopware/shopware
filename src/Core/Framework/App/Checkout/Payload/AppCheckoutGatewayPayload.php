<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Checkout\Payload;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\App\Payment\Payload\Struct\Source;
use Shopware\Core\Framework\App\Payment\Payload\Struct\SourcedPayloadInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class AppCheckoutGatewayPayload implements SourcedPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;

    protected Source $source;

    /**
     * @param string[] $paymentMethods
     * @param string[] $shippingMethods
     *
     * @internal
     */
    public function __construct(
        protected SalesChannelContext $salesChannelContext,
        protected Cart $cart,
        protected array $paymentMethods = [],
        protected array $shippingMethods = []
    ) {
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @return string[]
     */
    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    /**
     * @return string[]
     */
    public function getShippingMethods(): array
    {
        return $this->shippingMethods;
    }
}
