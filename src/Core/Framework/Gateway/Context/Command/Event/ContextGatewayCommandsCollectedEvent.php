<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Hook\CartAware;
use Shopware\Core\Framework\App\Context\Gateway\AppContextGateway;
use Shopware\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopware\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched after the app context gateway has collected all context commands.
 * It can be used to add custom commands, which should be executed with all other commands.
 *
 * @see AppContextGateway::process() for an example implementation
 */
#[Package('framework')]
class ContextGatewayCommandsCollectedEvent extends Event implements CartAware
{
    public function __construct(
        private readonly ContextGatewayPayloadStruct $payload,
        private readonly ContextGatewayCommandCollection $commands,
    ) {
    }

    public function getPayload(): ContextGatewayPayloadStruct
    {
        return $this->payload;
    }

    public function getCommands(): ContextGatewayCommandCollection
    {
        return $this->commands;
    }

    public function getCart(): Cart
    {
        return $this->payload->getCart();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->payload->getSalesChannelContext();
    }
}
