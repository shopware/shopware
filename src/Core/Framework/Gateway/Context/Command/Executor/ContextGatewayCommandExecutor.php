<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command\Executor;

use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopware\Core\Framework\Gateway\Context\Command\RegisterCustomerCommand;
use Shopware\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopware\Core\Framework\Gateway\Context\ContextGatewayException;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class ContextGatewayCommandExecutor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContextGatewayCommandRegistry $registry,
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly ExceptionLogger $logger,
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
    ) {
    }

    public function execute(ContextGatewayCommandCollection $commands, SalesChannelContext $context): ContextTokenResponse
    {
        $parameters = [];

        if ($register = $commands->getRegisterCommand()) {
            $this->registry->get($register::COMMAND_KEY)->handle($register, $context, $parameters);

            /** @var CustomerResponse $response */
            $response = $parameters['customerResponse'];

            $token = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
            $contextParameters = new SalesChannelContextServiceParameters($context->getSalesChannelId(), $token);

            $context = $this->salesChannelContextService->get($contextParameters);
        }

        foreach ($commands as $command) {
            // registration is done before
            if ($command instanceof RegisterCustomerCommand) {
                continue;
            }

            if (!$this->registry->has($command::getDefaultKeyName())) {
                $this->logger->logOrThrowException(ContextGatewayException::handlerNotFound($command::getDefaultKeyName()));
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
