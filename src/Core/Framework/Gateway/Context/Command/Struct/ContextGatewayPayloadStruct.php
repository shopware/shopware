<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Struct;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class ContextGatewayPayloadStruct extends Struct
{
    public function __construct(
        protected Cart $cart,
        protected SalesChannelContext $context,
        protected RequestDataBag $data = new RequestDataBag(),
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getData(): RequestDataBag
    {
        return $this->data;
    }
}
