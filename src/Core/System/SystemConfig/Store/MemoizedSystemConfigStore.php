<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Store;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('system-settings')]
final class MemoizedSystemConfigStore implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array[]
     */
    private array $configs = [];

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => [
                ['onValueChanged', 1500],
            ],
        ];
    }

    public function onValueChanged(SystemConfigChangedEvent $event): void
    {
        $this->removeConfig($event->getSalesChannelId());
    }

    public function setConfig(?string $salesChannelId, array $config): void
    {
        $this->configs[$this->getKey($salesChannelId)] = $config;
    }

    public function getConfig(?string $salesChannelId): ?array
    {
        return $this->configs[$this->getKey($salesChannelId)] ?? null;
    }

    public function removeConfig(?string $salesChannelId): void
    {
        if ($salesChannelId === null) {
            $this->reset();

            return;
        }

        unset($this->configs[$this->getKey($salesChannelId)]);
    }

    public function reset(): void
    {
        $this->configs = [];
    }

    private function getKey(?string $salesChannelId): string
    {
        return $salesChannelId ?? '_global_';
    }
}
