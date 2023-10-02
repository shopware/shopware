<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\_fixture;

use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\AbstractCheckoutGatewayCommand;
use Shopware\Core\Checkout\Gateway\Command\Handler\AbstractCheckoutGatewayCommandHandler;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversNothing]
#[Package('checkout')]
class TestCheckoutGatewayHandler extends AbstractCheckoutGatewayCommandHandler
{
    public static function supportedCommands(): array
    {
        return [TestCheckoutGatewayCommand::class, TestCheckoutGatewayFooCommand::class];
    }

    /**
     * @param TestCheckoutGatewayCommand|TestCheckoutGatewayFooCommand $command
     */
    public function handle(AbstractCheckoutGatewayCommand $command, CheckoutGatewayResponse $response, SalesChannelContext $context): void
    {
        if ($command instanceof TestCheckoutGatewayFooCommand) {
            return;
        }

        $paymentMethods = new PaymentMethodCollection();

        foreach ($command->paymentMethodTechnicalNames as $paymentMethodTechnicalName) {
            $paymentMethod = new PaymentMethodEntity();
            $paymentMethod->setId(Uuid::randomHex());
            $paymentMethod->setTechnicalName($paymentMethodTechnicalName);

            $paymentMethods->add($paymentMethod);
        }

        $response->setAvailablePaymentMethods($paymentMethods);
    }
}
