<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Context\Gateway;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Context\Payload\AppContextGatewayPayload;
use Shopware\Core\Framework\App\Context\Payload\AppContextGatewayPayloadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopware\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopware\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandExecutor;
use Shopware\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopware\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopware\Core\Framework\Gateway\Context\ContextGatewayException;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppContextGateway
{
    public function __construct(
        private readonly AppContextGatewayPayloadService $payloadService,
        private readonly ContextGatewayCommandExecutor $executor,
        private readonly ContextGatewayCommandRegistry $registry,
        private readonly EntityRepository $appRepository,
        private readonly ExceptionLogger $logger,
    ) {
    }

    public function process(ContextGatewayPayloadStruct $payload): ContextTokenResponse
    {
        if (!$payload->getData()->get('appName')) {
            $this->logger->logOrThrowException(ContextGatewayException::payloadInvalid('\'appName\' not found in payload'));

            return new ContextTokenResponse($payload->getContext()->getToken());
        }

        $appName = $payload->getData()->get('appName');
        $app = $this->getApp($appName, $payload->getContext()->getContext());

        $appPayload = new AppContextGatewayPayload($payload->getContext(), $payload->getCart(), $payload->getData()->all());

        /** @var string $checkoutGatewayUrl */
        $checkoutGatewayUrl = $app->getContextGatewayUrl();
        $appResponse = $this->payloadService->request($checkoutGatewayUrl, $appPayload, $app);

        if (!$appResponse) {
            $this->logger->logOrThrowException(ContextGatewayException::emptyAppResponse($app->getName()));

            return new ContextTokenResponse($payload->getContext()->getToken());
        }

        $commands = $this->collectCommandsFromAppResponse($appResponse);

        return $this->executor->execute($commands, $payload->getContext());
    }

    private function getApp(string $appName, Context $context): AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var AppEntity|null $app */
        $app = $this->appRepository->search($criteria, $context)->first();

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
                $this->logger->logOrThrowException(ContextGatewayException::payloadInvalid($payload['command'] ?? null));

                continue;
            }

            $commandKey = $payload['command'];

            if (!$this->registry->hasAppCommand($commandKey)) {
                $this->logger->logOrThrowException(ContextGatewayException::handlerNotFound($commandKey));

                continue;
            }

            $command = $this->registry->getAppCommand($commandKey);

            if (!\is_a($command, AbstractContextGatewayCommand::class, true)) {
                $this->logger->logOrThrowException(ContextGatewayException::handlerNotFound($commandKey));

                continue;
            }

            $commandPayload = $payload['payload'];

            try {
                $executableCommand = $command::createFromPayload($commandPayload);
            } catch (\Throwable) {
                $this->logger->logOrThrowException(ContextGatewayException::payloadInvalid($payload['command']));
                continue;
            }

            $collected->add($executableCommand);
        }

        return $collected;
    }
}
