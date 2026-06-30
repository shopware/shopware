<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Psr\Clock\ClockInterface;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Integration\IntegrationEntity;
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
        $lock = $this->registrationLock->acquire($appId);

        try {
            $app = $this->loadApp($appId, $context);

            // An unconfirmed secret from a previous rotation must be recovered first; rotating again would
            // overwrite the only record of the secret the app may already hold.
            if ($app->getUnconfirmedAppSecrets() !== null) {
                throw AppException::appSecretRotationAlreadyPending($app->getName());
            }

            $currentIntegrationId = $app->getIntegrationId();
            $currentIntegration = $app->getIntegration();
            \assert($currentIntegration !== null);

            $manifest = $this->resolveManifest($app);

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
                // app:secret:recover can re-register against it.
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
        } finally {
            $lock->release();
        }
    }

    public function findAppsWithUnconfirmedSecrets(Context $context): AppCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('unconfirmedAppSecrets', null));

        return $this->appRepository->search($criteria, $context)->getEntities();
    }

    /**
     * How many apps have an unconfirmed secret. This is the value reported as the "stuck rotation" metric.
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
     * Run by an operator to fix a rotation that ended without a clear answer (the confirm request timed out
     * or returned a 5xx). An unconfirmed secret was left behind, and we cannot tell whether the app switched to
     * the new credentials. We re-register the app with a fresh integration, signing the handshake first with
     * the unconfirmed secret (the one the app most likely holds) and, if that is refused, with the current one.
     * The first secret the app accepts wins. If the app refuses both, core no longer holds a secret the app
     * trusts and the registration cannot be recovered (the operator must generate a new shop id). No database
     * transaction is held open across the registration HTTP call.
     */
    public function recoverNow(string $appId, Context $context): void
    {
        $lock = $this->registrationLock->acquire($appId);

        try {
            $this->doRecover($appId, $context);
        } finally {
            $lock->release();
        }
    }

    private function doRecover(string $appId, Context $context): void
    {
        $app = $this->loadApp($appId, $context);

        $unconfirmed = $app->getUnconfirmedAppSecrets() ?? [];
        if ($unconfirmed === []) {
            throw AppException::appSecretRotationNothingToRecover($app->getName());
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

        $manifest = $this->resolveManifest($app);

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
                try {
                    $this->registrationService->reRegisterWithAppHeldSecret($manifest, $appId, $newSecret, $context, $appHeldSecret);

                    $this->logger->info('App secret recovered by re-registration', [
                        'appId' => $appId,
                        'appName' => $app->getName(),
                    ]);
                    $this->recordRecoveryOutcome('recovered');

                    return;
                } catch (AppException $e) {
                    if ($e->getErrorCode() !== AppException::APP_REGISTRATION_REJECTED) {
                        // No clear answer (5xx/timeout) or an unexpected failure — let the outer handler deal
                        // with it. We only move on to the next secret when the app definitively rejected this one.
                        throw $e;
                    }

                    // The app does not trust this secret; try the next candidate. The rejection reason is
                    // already logged in AppRegistrationService.
                }
            }
        } catch (\Throwable $e) {
            // An attempt failed without a clear answer (a 5xx/timeout, or a non-registration failure after the
            // integration switch). The outcome is unknown, so rethrow for the operator to retry — mirroring
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

        // The app refused every candidate secret, so core no longer holds a secret it trusts and the
        // registration cannot be recovered. Put the app back on the integration it had before recovery and
        // clear the now-useless unconfirmed list.
        $this->restorePreviousIntegrationAndDiscardSecrets(
            appId: $appId,
            currentIntegrationId: $currentIntegrationId,
            newIntegrationId: $newIntegrationId,
            context: $context,
        );
        $this->recordRecoveryOutcome('claimed');

        throw AppException::appSecretRotationClaimed($app->getName());
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
     * Like restorePreviousIntegration, but also drops the unconfirmed list. Used when recovery has exhausted
     * every candidate secret: the app trusts none of them, so the list is useless and must stop flagging the
     * app as stuck.
     */
    private function restorePreviousIntegrationAndDiscardSecrets(
        string $appId,
        string $currentIntegrationId,
        string $newIntegrationId,
        Context $context
    ): void {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($appId, $currentIntegrationId, $newIntegrationId): void {
            $this->appRepository->update([[
                'id' => $appId,
                'integrationId' => $currentIntegrationId,
                'unconfirmedAppSecrets' => null,
                'unconfirmedAppSecretsUpdatedAt' => null,
            ]], $context);

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

        $app = $this->appRepository->search($criteria, $context)->get($appId);
        if (!$app instanceof AppEntity) {
            throw AppException::notFoundByField($appId, 'id');
        }

        return $app;
    }

    private function resolveManifest(AppEntity $app): Manifest
    {
        $filesystem = $this->sourceResolver->filesystemForApp($app);

        return $this->manifestFactory->createFromXmlFile($filesystem->path('manifest.xml'));
    }
}
