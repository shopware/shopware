<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Handler;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SalesChannelContextAwareCommand
{
    public function handle(AbstractContextGatewayCommand $command, Cart $cart, SalesChannelContext $context): void;
}
