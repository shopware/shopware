<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event\Hooks;

use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\AppSpecificHook;

/**
 * Triggered when your app is updated.
 *
 * @hook-use-case app_lifecycle
 *
 * @since 6.4.9.0
 *
 * @final
 */
#[Package('core')]
class AppUpdatedHook extends AppLifecycleHook implements AppSpecificHook
{
    final public const HOOK_NAME = 'app-updated';

    private readonly AppUpdatedEvent $event;

    public function __construct(AppUpdatedEvent $event)
    {
        parent::__construct($event->getContext());
        $this->event = $event;
    }

    public function getEvent(): AppUpdatedEvent
    {
        return $this->event;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    /**
     * @internal
     */
    public function getAppId(): string
    {
        return $this->event->getApp()->getId();
    }
}
