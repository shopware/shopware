<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command\Executor;

use Shopware\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Gateway\Command\CheckoutGatewayCommandCollection;
use Shopware\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
final class CheckoutGatewayCommandExecutor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CheckoutGatewayCommandRegistry $registry,
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function execute(
        CheckoutGatewayCommandCollection $commands,
        CheckoutGatewayResponse $response,
        SalesChannelContext $context,
    ): CheckoutGatewayResponse {
        foreach ($commands as $command) {
            if (!$this->registry->has($command::getDefaultKeyName())) {
                $this->logger->logOrThrowException(CheckoutGatewayException::handlerNotFound($command::getDefaultKeyName()));
                continue;
            }

            $this->registry->get($command::getDefaultKeyName())->handle($command, $response, $context);
        }

        return $response;
    }
}
