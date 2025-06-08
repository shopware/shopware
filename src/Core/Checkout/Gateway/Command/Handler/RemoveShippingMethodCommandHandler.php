<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command\Handler;

use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Checkout\Gateway\Command\RemoveShippingMethodCommand;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class RemoveShippingMethodCommandHandler extends AbstractCheckoutGatewayCommandHandler
{
    public static function supportedCommands(): array
    {
        return [
            RemoveShippingMethodCommand::class,
        ];
    }

    /**
     * @param RemoveShippingMethodCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        $technicalName = $command->shippingMethodTechnicalName;
        $methods = $response->getAvailableShippingMethods();

        $methods = $methods->filter(function (ShippingMethodEntity $method) use ($technicalName) {
            return $method->getTechnicalName() !== $technicalName;
        });

        $response->setAvailableShippingMethods($methods);
    }
}
