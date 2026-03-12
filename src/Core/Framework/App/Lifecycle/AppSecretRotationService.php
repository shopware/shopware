<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\Message\RotateAppSecretMessage;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationCollection;
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
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     */
    public function __construct(
        private readonly AppRegistrationService $registrationService,
        private readonly EntityRepository $appRepository,
        private readonly EntityRepository $integrationRepository,
        private readonly SourceResolver $sourceResolver,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly ManifestFactory $manifestFactory,
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
        string $appId,
        Context $context,
        string $trigger
    ): void {
        $app = $this->loadApp($appId, $context);

        $currentIntegrationId = $app->getIntegrationId();
        $currentIntegration = $app->getIntegration();
        \assert($currentIntegration !== null);

        $manifest = $this->resolveManifest($app);

        $this->logger->info('Starting app secret rotation', [
            'appId' => $app->getId(),
            'appName' => $app->getName(),
            'trigger' => $trigger,
        ]);

        // Generate new access key and secret
        $newAccessKey = AccessKeyHelper::generateAccessKey('integration');
        $newSecret = AccessKeyHelper::generateSecretAccessKey();
        $newIntegrationId = Uuid::randomHex();

        $integrationUpdated = false;

        try {
            // Rotate the integration before, so that we minimize the changes inside the registration call.
            // This still works because the old integration is still valid until a scheduled cleanup deletes it.
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($app, $currentIntegration, $newAccessKey, $newSecret, $newIntegrationId, $currentIntegrationId): void {
                $this->appRepository->update([
                    [
                        'id' => $app->getId(),
                        'integration' => [
                            'id' => $newIntegrationId,
                            'label' => $currentIntegration->getLabel(),
                            'accessKey' => $newAccessKey,
                            'secretAccessKey' => $newSecret,
                        ],
                    ],
                ], $context);

                $this->integrationRepository->update([[
                    'id' => $currentIntegrationId,
                    'deletedAt' => new \DateTimeImmutable(),
                ]], $context);
            });
            $integrationUpdated = true;

            $this->registrationService->registerApp($manifest, $appId, $newSecret, $context);

            $this->logger->info('App secret rotation completed', [
                'appId' => $app->getId(),
                'appName' => $app->getName(),
                'trigger' => $trigger,
            ]);
        } catch (\Throwable $exception) {
            if ($integrationUpdated) {
                $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($app, $currentIntegrationId, $newIntegrationId): void {
                    $this->appRepository->update([[
                        'id' => $app->getId(),
                        'integrationId' => $currentIntegrationId,
                    ]], $context);

                    $this->integrationRepository->update([
                        [
                            'id' => $currentIntegrationId,
                            'deletedAt' => null,
                        ],
                        [
                            'id' => $newIntegrationId,
                            'deletedAt' => new \DateTimeImmutable(),
                        ],
                    ], $context);
                });
            }

            $this->logger->error('App secret rotation failed', [
                'appId' => $app->getId(),
                'appName' => $app->getName(),
                'trigger' => $trigger,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function loadApp(string $appId, Context $context): AppEntity
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('integration');

        $app = $this->appRepository->search($criteria, $context)->get($appId);
        if (!$app instanceof AppEntity) {
            throw AppException::notFoundByField('id', $appId);
        }

        return $app;
    }

    private function resolveManifest(AppEntity $app): Manifest
    {
        $filesystem = $this->sourceResolver->filesystemForApp($app);

        return $this->manifestFactory->createFromXmlFile($filesystem->path('manifest.xml'));
    }
}
