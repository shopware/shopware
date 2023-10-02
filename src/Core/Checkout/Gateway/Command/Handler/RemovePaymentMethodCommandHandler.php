<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command\Handler;

use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Checkout\Gateway\Command\RemovePaymentMethodCommand;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class RemovePaymentMethodCommandHandler extends AbstractCheckoutGatewayCommandHandler
{
    public static function supportedCommands(): array
    {
        return [
            RemovePaymentMethodCommand::class,
        ];
    }

    /**
     * @param RemovePaymentMethodCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        $technicalName = $command->paymentMethodTechnicalName;
        $methods = $response->getAvailablePaymentMethods();

        $methods = $methods->filter(function (PaymentMethodEntity $method) use ($technicalName) {
            return $method->getTechnicalName() !== $technicalName;
        });

        $response->setAvailablePaymentMethods($methods);
    }
}
