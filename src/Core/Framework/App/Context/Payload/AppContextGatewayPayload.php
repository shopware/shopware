<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Context\Payload;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\App\Payload\SourcedPayloadInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppContextGatewayPayload implements SourcedPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;

    protected Source $source;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        protected SalesChannelContext $salesChannelContext,
        protected Cart $cart,
        protected array $data = [],
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
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
