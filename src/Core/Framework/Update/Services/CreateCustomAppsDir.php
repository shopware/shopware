<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('system-settings')]
class CreateCustomAppsDir implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly string $appDir)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UpdatePostFinishEvent::class => 'onUpdate',
        ];
    }

    public function onUpdate(): void
    {
        if (is_dir($this->appDir)) {
            return;
        }

        mkdir($this->appDir);
    }
}
