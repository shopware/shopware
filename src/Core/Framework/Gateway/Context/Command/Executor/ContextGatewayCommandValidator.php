<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Executor;

use Shopware\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
class ContextGatewayCommandValidator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function validate(ContextGatewayCommandCollection $commands, SalesChannelContext $context): void
    {
        if ($commands->getTokenCommands()->count() > 1) {
            $this->logger->logOrThrowException(GatewayException::commandValidationFailed('Only one register or login command is allowed'));

            return;
        }

        $types = $commands->getCommandTypes();

        if (\count($types) !== \count(\array_unique($types))) {
            $this->logger->logOrThrowException(GatewayException::commandValidationFailed('Duplicate commands of a type are not allowed'));
        }
    }
}
