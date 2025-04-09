<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Handler;

use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\RegisterCustomerCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class RegisterCustomerCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractRegisterRoute $registerRoute,
    ) {
    }

    /**
     * @param RegisterCustomerCommand $command
     */
    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        $data = $command->data;
        $data['billing'] = new DataBag($data['billingAddress']);

        if (isset($data['shippingAddress'])) {
            $data['shipping'] = new DataBag($data['shippingAddress']);
        }

        if (isset($data['vatIds'])) {
            $data['vatIds'] = new DataBag($data['vatIds']);
        }

        $data = new RequestDataBag($data);

        $parameters['customerResponse'] = $this->registerRoute->register($data, $context);
    }

    public static function supportedCommands(): array
    {
        return [RegisterCustomerCommand::class];
    }
}
