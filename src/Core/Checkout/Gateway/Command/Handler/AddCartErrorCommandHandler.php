<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command\Handler;

use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Checkout\Gateway\Command\AddCartErrorCommand;
use Shopware\Core\Checkout\Gateway\Error\CheckoutGatewayError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class AddCartErrorCommandHandler extends AbstractCheckoutGatewayCommandHandler
{
    public static function supportedCommands(): array
    {
        return [
            AddCartErrorCommand::class,
        ];
    }

    /**
     * @param AddCartErrorCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        $response->getCartErrors()->add(new CheckoutGatewayError($command->message, $command->level, $command->blocking));
    }
}
