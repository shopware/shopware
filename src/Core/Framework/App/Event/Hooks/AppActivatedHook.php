<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event\Hooks;

use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\AppSpecificHook;

/**
 * Triggered when your app is activated.
 *
 * @hook-use-case app_lifecycle
 *
 * @since 6.4.9.0
 *
 * @final
 */
#[Package('core')]
class AppActivatedHook extends AppLifecycleHook implements AppSpecificHook
{
    final public const HOOK_NAME = 'app-activated';

    private readonly AppActivatedEvent $event;

    public function __construct(AppActivatedEvent $event)
    {
        parent::__construct($event->getContext());
        $this->event = $event;
    }

    public function getEvent(): AppActivatedEvent
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
