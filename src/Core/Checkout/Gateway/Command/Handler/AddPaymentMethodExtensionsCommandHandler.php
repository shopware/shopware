<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command\Handler;

use Shopware\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Checkout\Gateway\Command\AddPaymentMethodExtensionCommand;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class AddPaymentMethodExtensionsCommandHandler extends AbstractCheckoutGatewayCommandHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ExceptionLogger $logger,
    ) {
    }

    public static function supportedCommands(): array
    {
        return [
            AddPaymentMethodExtensionCommand::class,
        ];
    }

    /**
     * @param AddPaymentMethodExtensionCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        $method = $response->getAvailablePaymentMethods()->filter(function (PaymentMethodEntity $method) use ($command) {
            return $method->getTechnicalName() === $command->paymentMethodTechnicalName;
        })->first();

        if (!$method) {
            $this->logger->logOrThrowException(
                CheckoutGatewayException::handlerException('Payment method "{{ technicalName }}" not found', ['technicalName' => $command->paymentMethodTechnicalName])
            );

            return;
        }

        $method->addExtensions([$command->extensionKey => new ArrayStruct($command->extensionsPayload)]);
    }
}
