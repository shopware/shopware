<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\Message\RotateAppSecretMessage;
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
    public const TRIGGER_RECOVERY = 'recovery';

    /**
     * @param EntityRepository<AppCollection> $appRepository
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     */
    public function __construct(
        private readonly AppRegistrationService $registrationService,
        private readonly EntityRepository $appRepository,
        private readonly EntityRepository $integrationRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly ManifestFactory $manifestFactory,
        private readonly ClockInterface $clock,
        private readonly DeletedAppsGateway $deletedAppsGateway,
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
     *
     * A pending unconfirmed secret is not rotated over — it is the only record of a secret the app may
     * already hold — so the handshake is signed with every secret the app might still trust until the app
     * accepts one; a clean app reduces to a single attempt with its committed secret. Every failed attempt
     * walks on to the next candidate — a refused signature and an unreachable app are indistinguishable — and
     * {@see AppException::appSecretRecoveryFailed} is thrown if none is accepted. No database transaction is
     * held open across the registration HTTP call.
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

        $manifest = $this->manifestFactory->createFromApp($app);

        // A rotation repairs credentials; it cannot run the install lifecycle. Committing clears the
        // unconfirmed list, which is the only marker {@see AppManager::recoverInstallation} has to resume
        // an interrupted installation — so refuse rather than silently consume it.
        if ($trigger !== self::TRIGGER_RECOVERY
            && $manifest->getSetup() !== null
            && ($app->getAppSecret() === null || $this->deletedAppsGateway->getDeletedAppSecret($app->getName()) !== null)
        ) {
            throw AppException::appInstallationIncomplete($app->getName());
        }

        $candidateSecrets = $this->candidateSecrets($app);
        $unconfirmedBefore = $app->getUnconfirmedAppSecrets();

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
                    'deletedAt' => $this->clock->now(),
                ]], $context);
            });
            $integrationUpdated = true;

            foreach ($candidateSecrets as $appHeldSecret) {
                try {
                    $this->registrationService->reRegisterWithAppHeldSecret($manifest, $appId, $newSecret, $context, $appHeldSecret);

                    $this->logger->info('App secret rotation completed', [
                        'appId' => $app->getId(),
                        'appName' => $app->getName(),
                        'trigger' => $trigger,
                    ]);

                    return;
                } catch (AppRegistrationException $failure) {
                    // The app did not take this secret; try the next candidate. An app that cannot verify a
                    // signature answers 500 as readily as 4xx, so a failure without a clear answer must walk on
                    // too — anything already minted is recorded as unconfirmed, so the next candidate cannot
                    // lose it. This log line is the only place the individual failure survives.
                    $this->logger->warning('App did not accept a credential candidate', [
                        'appId' => $app->getId(),
                        'appName' => $app->getName(),
                        'trigger' => $trigger,
                        'error' => $failure->getMessage(),
                    ]);
                }
            }

            // Keep the unconfirmed list: a transient 4xx/WAF response can look like a definitive rejection, so a
            // later retry may still recover the app.
            throw AppException::appSecretRecoveryFailed($app->getName());
        } catch (\Throwable $exception) {
            // The confirm hands the app the fresh integration's credentials, and it is sent right after the
            // minted secret is stored — so an unchanged unconfirmed list means nothing reached the app and it
            // is still on the previous integration. Only then is switching back safe; keeping an integration
            // the app never received would let a cleanup delete the one it actually uses.
            if ($integrationUpdated && $this->loadApp($appId, $context)->getUnconfirmedAppSecrets() === $unconfirmedBefore) {
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
                            'deletedAt' => $this->clock->now(),
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

    /**
     * The secrets the app might still hold, tried most-recent first: the pending mints an interrupted
     * attempt left behind, then the committed secret as a fallback. An app that never registered holds
     * none — the single null candidate means one first-registration handshake, signed with nothing.
     *
     * @return non-empty-list<string|null>
     */
    private function candidateSecrets(AppEntity $app): array
    {
        $candidates = array_values(array_unique(array_filter(
            array_merge($app->getUnconfirmedAppSecrets() ?? [], [$app->getAppSecret()]),
            static fn (?string $secret): bool => $secret !== null && $secret !== ''
        )));

        return $candidates === [] ? [null] : $candidates;
    }

    private function loadApp(string $appId, Context $context): AppEntity
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('integration');

        $app = $this->appRepository->search($criteria, $context)->getEntities()->get($appId);
        if (!$app instanceof AppEntity) {
            throw AppException::notFoundByField($appId, 'id');
        }

        return $app;
    }
}
