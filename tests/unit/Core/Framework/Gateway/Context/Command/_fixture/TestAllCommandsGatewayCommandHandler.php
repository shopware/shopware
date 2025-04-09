<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\_fixture;

use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingAddressCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingLocationCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingMethodCommand;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\AbstractContextGatewayCommandHandler;
use Shopware\Core\Framework\Gateway\Context\Command\LoginCustomerCommand;
use Shopware\Core\Framework\Gateway\Context\Command\RegisterCustomerCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * Just for testing purposes.
 * Simply puts anything into parameters, which is provided in the command's data.
 * Supports all commands.
 */
#[CoversNothing]
#[Package('framework')]
class TestAllCommandsGatewayCommandHandler extends AbstractContextGatewayCommandHandler
{
    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        if ($command instanceof RegisterCustomerCommand) {
            $response = new CustomerResponse((new CustomerEntity())->assign($command->data));
            $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'hatoken');

            $parameters['customerResponse'] = $response;

            return;
        }

        if ($command instanceof LoginCustomerCommand) {
            $parameters['token'] = $command->customerEmail;

            return;
        }

        if ($command instanceof ChangeBillingAddressCommand) {
            $parameters['billingAddress'] = $command->addressId;

            return;
        }

        if ($command instanceof ChangeShippingAddressCommand) {
            $parameters['shippingAddress'] = $command->addressId;

            return;
        }

        if ($command instanceof ChangeShippingLocationCommand) {
            if ($command->countryIso !== null) {
                $parameters['countryId'] = $command->countryIso;
            }

            if ($command->countryStateIso !== null) {
                $parameters['countryStateId'] = $command->countryStateIso;
            }

            return;
        }

        if ($command instanceof ChangeShippingMethodCommand) {
            $parameters['shippingMethod'] = $command->technicalName;

            return;
        }

        if ($command instanceof ChangePaymentMethodCommand) {
            $parameters['paymentMethod'] = $command->technicalName;

            return;
        }

        if ($command instanceof ChangeLanguageCommand) {
            $parameters['languageId'] = $command->iso;

            return;
        }

        if ($command instanceof ChangeCurrencyCommand) {
            $parameters['currencyId'] = $command->iso;
        }
    }

    public static function supportedCommands(): array
    {
        return [
            ChangeBillingAddressCommand::class,
            ChangeCurrencyCommand::class,
            ChangeLanguageCommand::class,
            ChangePaymentMethodCommand::class,
            ChangeShippingAddressCommand::class,
            ChangeShippingLocationCommand::class,
            ChangeShippingMethodCommand::class,
            LoginCustomerCommand::class,
            RegisterCustomerCommand::class,
        ];
    }
}
