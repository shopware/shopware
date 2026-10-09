<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Event\CommercialLicenseProvidedEvent;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class LicenseProviderSubscriber implements EventSubscriberInterface
{
    public const CONFIG_STORE_LICENSE_KEY = 'core.store.licenseKey';

    public const CONFIG_STORE_LICENSE_HOST = 'core.store.licenseHost';

    public function __construct(
        private readonly SystemConfigService $config,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppActivatedEvent::class => 'serviceActivated',
            BeforeSystemConfigChangedEvent::class => 'licenseChanged',
        ];
    }

    public function licenseChanged(BeforeSystemConfigChangedEvent $event): void
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

        $this->eventDispatcher->dispatch(CommercialLicenseProvidedEvent::forAll($licenseKey, $licenseHost));
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
}
