<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event\Hooks;

use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\AppSpecificHook;

/**
 * Triggered when your app is deactivated.
 *
 * @hook-use-case app_lifecycle
 *
 * @since 6.4.9.0
 *
 * @final
 */
#[Package('core')]
class AppDeactivatedHook extends AppLifecycleHook implements AppSpecificHook
{
    final public const HOOK_NAME = 'app-deactivated';

    private readonly AppDeactivatedEvent $event;

    public function __construct(AppDeactivatedEvent $event)
    {
        parent::__construct($event->getContext());
        $this->event = $event;
    }

    public function getEvent(): AppDeactivatedEvent
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
