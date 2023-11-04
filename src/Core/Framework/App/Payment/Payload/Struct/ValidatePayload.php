<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ValidatePayload implements SourcedPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;
    use RemoveAppTrait;

    protected Source $source;

    public function __construct(
        protected Cart $cart,
        protected array $requestData,
        protected SalesChannelContext $salesChannelContext
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

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getRequestData(): array
    {
        return $this->requestData;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
