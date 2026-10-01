<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Handler;

use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ChangeShippingAddressCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<ChangeBillingAddressCommand|ChangeShippingAddressCommand>
 *
 * @internal
 */
#[Package('framework')]
class ChangeAddressCommandHandler extends AbstractContextGatewayCommandHandler
{
    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        if ($command instanceof ChangeBillingAddressCommand) {
            $parameters['billingAddressId'] = $command->addressId;
        }

        if ($command instanceof ChangeShippingAddressCommand) {
            $parameters['shippingAddressId'] = $command->addressId;
        }
    }

    public static function supportedCommands(): array
    {
        return [
            ChangeBillingAddressCommand::class,
            ChangeShippingAddressCommand::class,
        ];
    }
}
