<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Context\Gateway;

use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppEntity;
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
use Shopware\Core\Framework\Gateway\Context\ContextGatewayInterface;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppContextGateway implements ContextGatewayInterface
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
            $this->logger->logOrThrowException(new \RuntimeException('\'appName\' not found in payload'));
        }

        $appName = $payload->getData()->get('appName');
        $app = $this->getApp($appName, $payload->getContext()->getContext());

        $appPayload = new AppContextGatewayPayload($payload->getContext(), $payload->getCart(), $payload->getData()->all());

        /** @var string $checkoutGatewayUrl */
        $checkoutGatewayUrl = $app->getContextGatewayUrl();
        $appResponse = $this->payloadService->request($checkoutGatewayUrl, $appPayload, $app);

        if (!$appResponse) {
            $this->logger->logOrThrowException(ContextGatewayException::emptyAppResponse($app->getName()));
        }

        $commands = $this->collectCommandsFromAppResponse($appResponse);

        return $this->executor->execute($commands, $payload->getContext());
    }

    private function getApp(string $appName, Context $context): AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));

        /** @var AppEntity|null $app */
        $app = $this->appRepository->search($criteria, $context)->first();

        if (!$app) {
            $this->logger->logOrThrowException(new \RuntimeException(sprintf('App with name "%s" not found', $appName)));
        }

        if (!$app->getContextGatewayUrl()) {
            $this->logger->logOrThrowException(new \RuntimeException(sprintf('App with name "%s" has no context gateway url', $appName)));
        }

        if (!$app->isActive()) {
            $this->logger->logOrThrowException(new \RuntimeException(sprintf('App with name "%s" is not active', $appName)));
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
            } catch (\Error) {
                $this->logger->logOrThrowException(ContextGatewayException::payloadInvalid($payload['command']));
                continue;
            }

            $collected->add($executableCommand);
        }

        return $collected;
    }
}
