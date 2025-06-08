<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command\Handler;

use Shopware\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Checkout\Gateway\Command\AddShippingMethodExtensionCommand;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class AddShippingMethodExtensionsCommandHandler extends AbstractCheckoutGatewayCommandHandler
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
            AddShippingMethodExtensionCommand::class,
        ];
    }

    /**
     * @param AddShippingMethodExtensionCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        $method = $response->getAvailableShippingMethods()->filter(function (ShippingMethodEntity $method) use ($command) {
            return $method->getTechnicalName() === $command->shippingMethodTechnicalName;
        })->first();

        if (!$method) {
            $this->logger->logOrThrowException(
                CheckoutGatewayException::handlerException('Shipping method "{{ technicalName }}" not found', ['technicalName' => $command->shippingMethodTechnicalName])
            );

            return;
        }

        $method->addExtensions([$command->extensionKey => new ArrayStruct($command->extensionsPayload)]);
    }
}
