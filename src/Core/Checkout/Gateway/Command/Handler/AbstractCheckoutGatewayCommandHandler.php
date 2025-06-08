<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command\Handler;

use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractCheckoutGatewayCommandHandler
{
    abstract public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void;

    /**
     * @return array<class-string<AbstractCheckoutGatewayCommand>>
     */
    abstract public static function supportedCommands(): array;
}
