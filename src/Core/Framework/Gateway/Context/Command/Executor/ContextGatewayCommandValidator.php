<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Executor;

use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopware\Core\Framework\Gateway\Context\Command\LoginCustomerCommand;
use Shopware\Core\Framework\Gateway\Context\Command\RegisterCustomerCommand;
use Shopware\Core\Framework\Gateway\Context\ContextGatewayException;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class ContextGatewayCommandValidator
{
    public function __construct(
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function validate(ContextGatewayCommandCollection $commands, SalesChannelContext $context): void
    {
        $registerCommands = $commands->filter(static fn (AbstractContextGatewayCommand $command) => $command instanceof RegisterCustomerCommand);

        if ($registerCommands->count() > 1) {
            $this->logger->logOrThrowException(ContextGatewayException::commandValidationFailed('Only one register command is allowed'));
        }

        $loginCommands = $commands->filter(static fn (AbstractContextGatewayCommand $command) => $command instanceof LoginCustomerCommand);

        if ($loginCommands->count() > 1) {
            $this->logger->logOrThrowException(ContextGatewayException::commandValidationFailed('Only one login command is allowed'));
        }

        if ($registerCommands->count() > 0 && $loginCommands->count() > 0) {
            $this->logger->logOrThrowException(ContextGatewayException::commandValidationFailed('Register and login commands cannot be used together'));
        }

        $types = $commands->map(static fn (AbstractContextGatewayCommand $command) => $command::getDefaultKeyName());

        if (\count($types) !== \count(\array_unique($types))) {
            $this->logger->logOrThrowException(ContextGatewayException::commandValidationFailed('Duplicate commands of a type are not allowed'));
        }
    }
}
