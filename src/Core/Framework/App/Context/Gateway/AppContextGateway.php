<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Context\Gateway;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Context\Payload\AppContextGatewayPayloadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopware\Core\Framework\Gateway\Context\Command\Event\ContextGatewayCommandsCollectedEvent;
use Shopware\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandExecutor;
use Shopware\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopware\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppContextGateway
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly AppContextGatewayPayloadService $payloadService,
        private readonly ContextGatewayCommandExecutor $executor,
        private readonly ContextGatewayCommandRegistry $registry,
        private readonly EntityRepository $appRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function process(ContextGatewayPayloadStruct $payload): ContextTokenResponse
    {
        $appName = $payload->getData()->get('appName');
        if (!$appName) {
            throw AppException::missingRequestParameter('appName');
        }
        $app = $this->getApp($appName, $payload->getSalesChannelContext()->getContext());

        $contextGatewayUrl = $app->getContextGatewayUrl();

        if (!$contextGatewayUrl) {
            throw AppException::gatewayNotConfigured($app->getName(), 'context');
        }

        $appResponse = $this->payloadService->request($contextGatewayUrl, $payload, $app);

        if (!$appResponse) {
            throw AppException::gatewayRequestFailed($app->getName(), 'context');
        }

        $commands = $this->collectCommandsFromAppResponse($appResponse);

        $this->eventDispatcher->dispatch(new ContextGatewayCommandsCollectedEvent($payload, $commands));

        return $this->executor->execute($commands, $payload->getSalesChannelContext());
    }

    private function getApp(string $appName, Context $context): AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));
        $criteria->addFilter(new EqualsFilter('active', true));

        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();

        if (!$app) {
            throw AppException::appNotFoundByName($appName);
        }

        if (!$app->getContextGatewayUrl()) {
            throw AppException::gatewayNotConfigured($appName, 'context');
        }

        return $app;
    }

    private function collectCommandsFromAppResponse(AppContextGatewayResponse $response): ContextGatewayCommandCollection
    {
        $collected = new ContextGatewayCommandCollection();

        foreach ($response->getCommands() as $payload) {
            if (!isset($payload['command'], $payload['payload'])) {
                $this->logger->logOrThrowException(GatewayException::payloadInvalid($payload['command'] ?? null));

                continue;
            }

            $commandKey = $payload['command'];

            if (!$this->registry->hasAppCommand($commandKey)) {
                $this->logger->logOrThrowException(GatewayException::handlerNotFound($commandKey));

                continue;
            }

            $command = $this->registry->getAppCommand($commandKey);

            if (!\is_a($command, AbstractContextGatewayCommand::class, true)) {
                $this->logger->logOrThrowException(GatewayException::handlerNotFound($commandKey));

                continue;
            }

            $commandPayload = $payload['payload'];

            try {
                $executableCommand = $command::createFromPayload($commandPayload);
            } catch (\Throwable) {
                $this->logger->logOrThrowException(GatewayException::payloadInvalid($payload['command']));
                continue;
            }

            $collected->add($executableCommand);
        }

        return $collected;
    }
}
