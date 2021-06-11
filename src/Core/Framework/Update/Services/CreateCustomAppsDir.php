<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreateCustomAppsDir implements EventSubscriberInterface
{
    private string $appDir;

    public function __construct(string $appDir)
    {
        $this->appDir = $appDir;
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
