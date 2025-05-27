<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Executor;

use Shopware\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopware\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopware\Core\Framework\Gateway\Context\Command\TokenCommandInterface;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
class ContextGatewayCommandExecutor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly ContextGatewayCommandRegistry $registry,
        private readonly ContextGatewayCommandValidator $commandValidator,
        private readonly ExceptionLogger $logger,
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
    ) {
    }

    public function execute(ContextGatewayCommandCollection $commands, SalesChannelContext $context): ContextTokenResponse
    {
        $this->commandValidator->validate($commands, $context);

        $parameters = [];

        if ($tokenCommand = $commands->getSingleTokenCommand()) {
            /** @phpstan-ignore symplify.noDynamicName */
            $this->registry->get($tokenCommand::COMMAND_KEY)->handle($tokenCommand, $context, $parameters);

            $token = $parameters['token'];
            unset($parameters['token']);

            $contextParameters = new SalesChannelContextServiceParameters($context->getSalesChannelId(), $token);
            $context = $this->salesChannelContextService->get($contextParameters);
        }

        foreach ($commands as $command) {
            // these commands are already handled
            if ($command instanceof TokenCommandInterface) {
                continue;
            }

            if (!$this->registry->has($command::getDefaultKeyName())) {
                $this->logger->logOrThrowException(GatewayException::handlerNotFound($command::getDefaultKeyName()));
                continue;
            }

            $this->registry->get($command::getDefaultKeyName())->handle($command, $context, $parameters);
        }

        $response = new ContextTokenResponse($context->getToken());

        if (!empty($parameters)) {
            $response = $this->contextSwitchRoute->switchContext(new RequestDataBag($parameters), $context);
        }

        return $response;
    }
}
