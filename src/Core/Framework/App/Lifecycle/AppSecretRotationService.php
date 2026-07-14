<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppRegistrationRejectedException;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\Message\RotateAppSecretMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Integration\IntegrationEntity;
use Symfony\Component\Lock\LockInterface;
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
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly ManifestFactory $manifestFactory,
        private readonly ClockInterface $clock,
        private readonly AppRegistrationLock $registrationLock,
        private readonly Meter $meter,
    ) {
    }

    public function scheduleRotation(AppEntity $app, string $trigger): void
    {
        // Reject up front so the caller sees it synchronously; the queued handler can't surface this to them.
        // rotateNow re-checks under the lock — this is fail-fast, not the authoritative guard.
        if ($app->getUnconfirmedAppSecrets() !== null) {
            throw AppException::appSecretRotationAlreadyPending($app->getName());
        }

        $this->logger->info('Scheduling app secret rotation', [
            'appId' => $app->getId(),
            'appName' => $app->getName(),
            'trigger' => $trigger,
        ]);

        $message = new RotateAppSecretMessage($app->getId(), $trigger);
        $this->messageBus->dispatch($message);
    }

    public function rotateNow(
        string $appId,
        Context $context,
        string $trigger
    ): void {
        $this->registrationLock->locked($appId, function () use ($appId, $context, $trigger): void {
            $app = $this->loadApp($appId, $context);

            // An unconfirmed secret from a previous rotation must be recovered first; rotating again would
            // overwrite the only record of the secret the app may already hold.
            if ($app->getUnconfirmedAppSecrets() !== null) {
                throw AppException::appSecretRotationAlreadyPending($app->getName());
            }

            $currentIntegrationId = $app->getIntegrationId();
            $currentIntegration = $app->getIntegration();
            \assert($currentIntegration !== null);

            $manifest = $this->manifestFactory->createFromApp($app);

            $this->logger->info('Starting app secret rotation', [
                'appId' => $app->getId(),
                'appName' => $app->getName(),
                'trigger' => $trigger,
            ]);

            $newAccessKey = AccessKeyHelper::generateAccessKey('integration');
            $newSecret = AccessKeyHelper::generateSecretAccessKey();
            $newIntegrationId = Uuid::randomHex();

            $this->switchToNewIntegration(
                appId: $appId,
                currentIntegration: $currentIntegration,
                currentIntegrationId: $currentIntegrationId,
                newIntegrationId: $newIntegrationId,
                newAccessKey: $newAccessKey,
                newSecret: $newSecret,
                context: $context,
            );

            try {
                $this->registrationService->registerApp($manifest, $appId, $newSecret, $context);

                $this->logger->info('App secret rotation completed', [
                    'appId' => $app->getId(),
                    'appName' => $app->getName(),
                    'trigger' => $trigger,
                ]);
            } catch (\Throwable $exception) {
                // Switch the app back to the integration it used before this rotation — unless the confirm
                // was uncertain and left an unconfirmed secret, in which case the new integration must stay so
                // a later app installation can re-register against it.
                if ($this->loadApp($appId, $context)->getUnconfirmedAppSecrets() === null) {
                    $this->restorePreviousIntegration(
                        appId: $appId,
                        currentIntegrationId: $currentIntegrationId,
                        newIntegrationId: $newIntegrationId,
                        context: $context,
                    );
                }

                $this->logger->error('App secret rotation failed', [
                    'appId' => $app->getId(),
                    'appName' => $app->getName(),
                    'trigger' => $trigger,
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        });
    }

    /**
     * How many apps have an unconfirmed secret. This is the value reported as the pending registration metric.
     * Counts rows only, without loading the full app entities.
     */
    public function countAppsWithUnconfirmedSecrets(Context $context): int
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addFilter(new NotEqualsFilter('unconfirmedAppSecrets', null));

        return $this->appRepository->searchIds($criteria, $context)->getTotal();
    }

    /**
     * Repairs a registration that ended without a clear answer (the confirm request timed out or returned a
     * 5xx). Installation calls this while holding the per-app lock; direct callers may let this method acquire
     * it. The expected verdicts are returned as a result, while an unknown outcome throws so installation can
     * be retried. No database transaction is held open across the registration HTTP call.
     */
    public function recoverNow(string $appId, Context $context, ?LockInterface $lock = null): AppSecretRecoveryResult
    {
        if ($lock !== null) {
            return $this->doRecover($appId, $context, $lock);
        }

        return $this->registrationLock->locked(
            $appId,
            fn (LockInterface $lock) => $this->doRecover($appId, $context, $lock)
        );
    }

    /**
     * Drop the unconfirmed secrets before the explicit new-identity shop-ID strategy re-registers the app.
     * This is destructive and must not be used for same-identity moves, which need the candidates for repair.
     */
    public function discardNow(string $appId, Context $context): void
    {
        $this->registrationLock->locked($appId, function () use ($appId, $context): void {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($appId): void {
                $this->appRepository->update([[
                    'id' => $appId,
                    'unconfirmedAppSecrets' => null,
                    'unconfirmedAppSecretsUpdatedAt' => null,
                ]], $context);
            });
        });
    }

    private function doRecover(string $appId, Context $context, LockInterface $lock): AppSecretRecoveryResult
    {
        $app = $this->loadApp($appId, $context);

        $unconfirmed = $app->getUnconfirmedAppSecrets() ?? [];
        if ($unconfirmed === []) {
            return AppSecretRecoveryResult::NothingToRecover;
        }

        // Try every secret the app might still hold, most-recent first: the unconfirmed list, then the
        // committed secret as a fallback for a rotation that never took.
        $candidateSecrets = array_values(array_unique(array_filter(
            array_merge($unconfirmed, [$app->getAppSecret()]),
            static fn (?string $secret): bool => $secret !== null && $secret !== ''
        )));

        $currentIntegrationId = $app->getIntegrationId();
        $currentIntegration = $app->getIntegration();
        \assert($currentIntegration !== null);

        $manifest = $this->manifestFactory->createFromApp($app);

        // Recovery re-registers, which needs <setup>. If the manifest no longer declares it, the app cannot
        // be re-registered — fail before switching integrations rather than no-op into a false success.
        if (!$manifest->getSetup()) {
            throw AppException::invalidArgument(\sprintf(
                'App "%s" has an unconfirmed secret but its manifest no longer declares <setup>; it cannot be recovered by re-registration.',
                $app->getName()
            ));
        }

        // One fresh integration for the recovery, reused across every candidate attempt below.
        $newAccessKey = AccessKeyHelper::generateAccessKey('integration');
        $newSecret = AccessKeyHelper::generateSecretAccessKey();
        $newIntegrationId = Uuid::randomHex();

        $this->switchToNewIntegration(
            appId: $appId,
            currentIntegration: $currentIntegration,
            currentIntegrationId: $currentIntegrationId,
            newIntegrationId: $newIntegrationId,
            newAccessKey: $newAccessKey,
            newSecret: $newSecret,
            context: $context,
        );

        try {
            foreach ($candidateSecrets as $appHeldSecret) {
                $this->registrationLock->refresh($lock, $appId);

                try {
                    $this->registrationService->reRegisterWithAppHeldSecret($manifest, $appId, $newSecret, $context, $appHeldSecret);

                    $this->logger->info('App secret recovered by re-registration', [
                        'appId' => $appId,
                        'appName' => $app->getName(),
                    ]);
                    $this->recordRecoveryOutcome(AppSecretRecoveryResult::Recovered->value);

                    return AppSecretRecoveryResult::Recovered;
                } catch (AppRegistrationRejectedException) {
                    // The app definitively rejected this secret; try the next candidate. The rejection reason
                    // is already logged in AppRegistrationService. Any other outcome (5xx/timeout/unexpected)
                    // is not caught here and propagates to the outer handler unchanged.
                }
            }
        } catch (\Throwable $e) {
            // An attempt failed without a clear answer (a 5xx/timeout, or a non-registration failure after the
            // integration switch). The outcome is unknown, so rethrow for installation to retry — mirroring
            // rotateNow's \Throwable handling — and let settle decide whether to undo the integration switch.
            $this->settleAmbiguousRecovery(
                appId: $appId,
                originalUnconfirmed: $unconfirmed,
                currentIntegrationId: $currentIntegrationId,
                newIntegrationId: $newIntegrationId,
                context: $context,
            );
            $this->recordRecoveryOutcome('unknown');

            throw $e;
        }

        // The app refused every candidate secret. Put the app back on the integration it had before recovery
        // but keep the unconfirmed list: a transient 4xx/WAF/proxy response can look like a definitive
        // rejection, and a later retry may still recover the app.
        $this->restorePreviousIntegration(
            appId: $appId,
            currentIntegrationId: $currentIntegrationId,
            newIntegrationId: $newIntegrationId,
            context: $context,
        );
        $this->recordRecoveryOutcome(AppSecretRecoveryResult::Claimed->value);

        return AppSecretRecoveryResult::Claimed;
    }

    private function recordRecoveryOutcome(string $outcome): void
    {
        $this->meter->emit(new ConfiguredMetric('app.secret_recovery.outcome.count', 1, ['outcome' => $outcome]));
    }

    /**
     * Undo the integration switch only when no confirm could have delivered the new credentials. A successful
     * handshake prepends a freshly minted secret, so an unconfirmed list that changed since recovery started
     * means a confirm was sent — keep the new integration; an unchanged list means the handshake itself failed
     * — revert. The list is left as-is either way; it records every secret a later retry must try.
     *
     * @param list<string> $originalUnconfirmed
     */
    private function settleAmbiguousRecovery(
        string $appId,
        #[\SensitiveParameter]
        array $originalUnconfirmed,
        string $currentIntegrationId,
        string $newIntegrationId,
        Context $context
    ): void {
        $unconfirmedAfterAttempts = $this->loadApp($appId, $context)->getUnconfirmedAppSecrets() ?? [];
        if ($unconfirmedAfterAttempts !== $originalUnconfirmed) {
            return;
        }

        $this->restorePreviousIntegration(
            appId: $appId,
            currentIntegrationId: $currentIntegrationId,
            newIntegrationId: $newIntegrationId,
            context: $context,
        );
    }

    /**
     * Roll back an integration switch: point the app back at the integration it had before, un-delete it, and
     * soft-delete the fresh one. The unconfirmed list is left untouched — a later recovery retry still needs it.
     */
    private function restorePreviousIntegration(
        string $appId,
        string $currentIntegrationId,
        string $newIntegrationId,
        Context $context
    ): void {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($appId, $currentIntegrationId, $newIntegrationId): void {
            $this->appRepository->update([['id' => $appId, 'integrationId' => $currentIntegrationId]], $context);

            $this->integrationRepository->update([
                ['id' => $currentIntegrationId, 'deletedAt' => null],
                ['id' => $newIntegrationId, 'deletedAt' => $this->clock->now()],
            ], $context);
        });
    }

    /**
     * Move the app onto a freshly minted integration and retire the old one. Both rotation and recovery do
     * this before re-registering, so the confirm can hand the app its new credentials. No compare-and-set is
     * needed: the per-app lock means no other rotation or recovery is touching this app at the same time.
     */
    private function switchToNewIntegration(
        string $appId,
        IntegrationEntity $currentIntegration,
        string $currentIntegrationId,
        string $newIntegrationId,
        string $newAccessKey,
        #[\SensitiveParameter]
        string $newSecret,
        Context $context
    ): void {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($appId, $currentIntegration, $currentIntegrationId, $newIntegrationId, $newAccessKey, $newSecret): void {
            $this->appRepository->update([[
                'id' => $appId,
                'integration' => [
                    'id' => $newIntegrationId,
                    'label' => $currentIntegration->getLabel(),
                    'accessKey' => $newAccessKey,
                    'secretAccessKey' => $newSecret,
                ],
            ]], $context);

            $this->integrationRepository->update([['id' => $currentIntegrationId, 'deletedAt' => $this->clock->now()]], $context);
        });
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
