<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Handler;

use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\LoginCustomerCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends AbstractContextGatewayCommandHandler<LoginCustomerCommand>
 *
 * @internal
 */
#[Package('framework')]
class LoginCustomerCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
    ) {
    }

    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        $customer = $this->accountService->getCustomerByEmail($command->customerEmail, $context);
        $parameters['token'] = $this->accountService->loginById($customer->getId(), $context);
    }

    public static function supportedCommands(): array
    {
        return [LoginCustomerCommand::class];
    }
}
