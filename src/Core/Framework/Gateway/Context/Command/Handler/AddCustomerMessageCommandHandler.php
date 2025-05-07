<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Handler;

use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\AddCustomerMessageCommand;
use Shopware\Core\Framework\Gateway\Context\ContextGatewayException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class AddCustomerMessageCommandHandler extends AbstractContextGatewayCommandHandler
{
    /**
     * @param AddCustomerMessageCommand $command
     */
    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        throw ContextGatewayException::customerMessage($command->message);
    }

    public static function supportedCommands(): array
    {
        return [AddCustomerMessageCommand::class];
    }
}
