<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Event\CommercialLicenseProvidedEvent;
use Shopware\Core\Service\ServiceClientFactory;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class LicenseSyncSubscriber implements EventSubscriberInterface
{
    public const CONFIG_STORE_LICENSE_KEY = 'core.store.licenseKey';

    public const CONFIG_STORE_LICENSE_HOST = 'core.store.licenseHost';

    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly SystemConfigService $config,
        private readonly Client $serviceRegistryClient,
        private readonly EntityRepository $appRepository,
        private readonly LoggerInterface $logger,
        private readonly ServiceClientFactory $clientFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // @deprecated tag:v6.8.0 - remove the install/update legacy license sync fallback.
            AppInstalledEvent::class => 'serviceInstalled',
            AppUpdatedEvent::class => 'serviceInstalled',
            AppActivatedEvent::class => 'serviceActivated',
            BeforeSystemConfigChangedEvent::class => 'syncLicense',
        ];
    }

    public function syncLicense(BeforeSystemConfigChangedEvent $event): void
    {
        $key = $event->getKey();
        $value = $event->getValue();

        if (!\in_array($key, [self::CONFIG_STORE_LICENSE_KEY, self::CONFIG_STORE_LICENSE_HOST], true) || !\is_string($value)) {
            return;
        }

        // the event doesn't mean that the value is different from the current one.
        // it could be just a rewrite of the same value.
        $updatedConfig = $this->config->getString($key);
        if ($value === $updatedConfig) {
            return;
        }

        $licenseKey = $key === self::CONFIG_STORE_LICENSE_KEY ? $value : $this->config->getString(self::CONFIG_STORE_LICENSE_KEY);
        $licenseHost = $key === self::CONFIG_STORE_LICENSE_HOST ? $value : $this->config->getString(self::CONFIG_STORE_LICENSE_HOST);

        /** @deprecated tag:v6.8.0 - remove the legacy endpoint sync and keep only the `commercial_license.provided` webhook. */
        $this->syncLicenseByLegacyEndpoint($licenseKey, $licenseHost);
        $this->eventDispatcher->dispatch(CommercialLicenseProvidedEvent::forAll($licenseKey, $licenseHost));
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-subscriber - Will be removed with the legacy commercial license sync endpoint support.
     */
    public function serviceInstalled(AppInstalledEvent|AppUpdatedEvent $event): void
    {
        $app = $event->getApp();
        $context = $event->getContext();
        $source = $context->getSource();

        if (!$app->getAppSecret() || !$app->isSelfManaged()) {
            return;
        }

        if ($source instanceof AdminApiSource && $app->getIntegrationId() !== $source->getIntegrationId()) {
            return;
        }

        if ($this->manifestDefinesWebhook($event->getManifest())) {
            return;
        }

        $this->syncLicenseByService(
            $app,
            $context,
            $this->config->getString(self::CONFIG_STORE_LICENSE_KEY),
            $this->config->getString(self::CONFIG_STORE_LICENSE_HOST)
        );
    }

    public function serviceActivated(AppActivatedEvent $event): void
    {
        $app = $event->getApp();

        if (!$app->isSelfManaged()) {
            return;
        }

        $this->eventDispatcher->dispatch(CommercialLicenseProvidedEvent::forService(
            $app->getId(),
            $this->config->getString(self::CONFIG_STORE_LICENSE_KEY),
            $this->config->getString(self::CONFIG_STORE_LICENSE_HOST),
        ));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the legacy commercial license sync endpoint support.
     */
    private function syncLicenseByLegacyEndpoint(string $licenseKey, string $licenseHost): void
    {
        $context = Context::createDefaultContext();

        $criteria = (new Criteria())
            ->addAssociation('webhooks')
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('selfManaged', true));

        $apps = $this->appRepository->search($criteria, $context)->getEntities();

        foreach ($apps as $app) {
            if (!$app->getAppSecret() || !$app->isSelfManaged() || $this->appDefinedWebhook($app)) {
                continue;
            }

            $this->syncLicenseByService($app, $context, $licenseKey, $licenseHost);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the legacy commercial license sync endpoint support.
     */
    private function syncLicenseByService(AppEntity $app, Context $context, string $licenseKey, string $licenseHost): void
    {
        try {
            $serviceEntry = $this->serviceRegistryClient->get($app->getName());

            if ($serviceEntry->licenseSyncEndPoint === null) {
                return;
            }

            $client = $this->clientFactory->newAuthenticatedFor($serviceEntry, $app, $context);

            $client->syncLicense($licenseKey, $licenseHost);
        } catch (\Throwable $e) {
            $this->logger->warning('Could not sync license', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the legacy commercial license sync endpoint support.
     */
    private function appDefinedWebhook(AppEntity $app): bool
    {
        $webhooks = $app->getWebhooks();

        return $webhooks !== null && $webhooks->filterForEvent(CommercialLicenseProvidedEvent::NAME)->count() > 0;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the install/update legacy license sync fallback.
     */
    private function manifestDefinesWebhook(Manifest $manifest): bool
    {
        $webhooks = $manifest->getWebhooks();
        if ($webhooks === null) {
            return false;
        }

        foreach ($webhooks->getWebhooks() as $webhook) {
            if ($webhook->getEvent() === CommercialLicenseProvidedEvent::NAME) {
                return true;
            }
        }

        return false;
    }
}
