<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppSecretRotatedEvent;
use Shopware\Core\Framework\App\Event\AppSecretRotationFailedEvent;
use Shopware\Core\Framework\App\Event\AppSecretRotationStartedEvent;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Message\RotateAppSecretMessage;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppSecretRotationService
{
    public const TRIGGER_API = 'api';
    public const TRIGGER_CLI = 'cli';
    public const TRIGGER_SHOP_MOVE = 'shop_move';

    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly AppRegistrationService $registrationService,
        private readonly EntityRepository $appRepository,
        private readonly SourceResolver $sourceResolver,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Schedule an asynchronous secret rotation via message queue
     * Used by API endpoint for non-blocking rotation
     */
    public function scheduleRotation(AppEntity $app, string $trigger): void
    {
        $this->logger->info('Scheduling app secret rotation', [
            'appId' => $app->getId(),
            'appName' => $app->getName(),
            'trigger' => $trigger,
        ]);

        $message = new RotateAppSecretMessage($app->getId(), $trigger);
        $this->messageBus->dispatch($message);
    }

    /**
     * Perform immediate synchronous secret rotation
     * Used by CLI commands and message queue handler
     */
    public function rotateNow(
        AppEntity $app,
        Context $context,
        string $trigger
    ): void {
        $app = $this->reloadApp($app->getId(), $context);
        $manifest = $this->resolveManifest($app);

        $this->dispatchStarted($app, $context, $trigger);
        $this->logger->info('Starting app secret rotation', [
            'appId' => $app->getId(),
            'appName' => $app->getName(),
            'trigger' => $trigger,
        ]);

        $secret = AccessKeyHelper::generateSecretAccessKey();
        $this->updateIntegrationCredentials($app, $secret, $context);

        try {
            $this->registrationService->registerApp($manifest, $app->getId(), $secret, $context);

            $updatedApp = $this->reloadApp($app->getId(), $context);
            $this->dispatchCompleted($updatedApp, $context, $trigger);

            $this->logger->info('App secret rotation completed', [
                'appId' => $updatedApp->getId(),
                'appName' => $updatedApp->getName(),
                'trigger' => $trigger,
            ]);
        } catch (\Throwable $exception) {
            $this->dispatchFailed($app, $context, $trigger, $exception);

            $this->logger->error('App secret rotation failed', [
                'appId' => $app->getId(),
                'appName' => $app->getName(),
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function reloadApp(string $appId, Context $context): AppEntity
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('integration');

        $app = $this->appRepository->search($criteria, $context)->get($appId);
        \assert($app instanceof AppEntity);

        return $app;
    }

    private function resolveManifest(AppEntity $app): Manifest
    {
        $filesystem = $this->sourceResolver->filesystemForApp($app);
        $path = $filesystem->hasFile('manifest.local.xml') ? 'manifest.local.xml' : 'manifest.xml';

        return Manifest::createFromXmlFile($filesystem->path($path));
    }

    private function updateIntegrationCredentials(AppEntity $app, #[\SensitiveParameter] string $secret, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($app, $secret): void {
            $this->appRepository->update([
                [
                    'id' => $app->getId(),
                    'integration' => [
                        'id' => $app->getIntegrationId(),
                        'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                        'secretAccessKey' => $secret,
                    ],
                ],
            ], $context);
        });
    }

    private function dispatchStarted(AppEntity $app, Context $context, string $trigger): void
    {
        $this->eventDispatcher?->dispatch(new AppSecretRotationStartedEvent($app, $context, $trigger));
    }

    private function dispatchCompleted(AppEntity $app, Context $context, string $trigger): void
    {
        $this->eventDispatcher?->dispatch(new AppSecretRotatedEvent($app, $context, $trigger));
    }

    private function dispatchFailed(AppEntity $app, Context $context, string $trigger, \Throwable $exception): void
    {
        $this->eventDispatcher?->dispatch(new AppSecretRotationFailedEvent($app, $context, $trigger, $exception));
    }
}
