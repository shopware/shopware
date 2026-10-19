<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Event\SystemHeartbeatEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookActivatedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookDegradedEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookDisabledEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookSuspendedEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook;

#[Package('framework')]
interface Hookable
{
    public const HOOKABLE_EVENTS = [
        MediaUploadedEvent::class => MediaUploadedEvent::EVENT_NAME,
        AppActivatedEvent::class => AppActivatedEvent::NAME,
        AppDeactivatedEvent::class => AppDeactivatedEvent::NAME,
        AppDeletedEvent::class => AppDeletedEvent::NAME,
        AppInstalledEvent::class => AppInstalledEvent::NAME,
        AppUpdatedEvent::class => AppUpdatedEvent::NAME,
        AppPermissionsUpdated::class => AppPermissionsUpdated::NAME,
        UpdatePostFinishEvent::class => UpdatePostFinishEvent::EVENT_NAME,
        SystemConfigChangedHook::class => SystemConfigChangedHook::EVENT_NAME,
        SystemHeartbeatEvent::class => SystemHeartbeatEvent::NAME,
        WebhookActivatedEvent::class => WebhookActivatedEvent::NAME,
        WebhookDegradedEvent::class => WebhookDegradedEvent::NAME,
        WebhookSuspendedEvent::class => WebhookSuspendedEvent::NAME,
        WebhookDisabledEvent::class => WebhookDisabledEvent::NAME,
    ];

    public const HOOKABLE_EVENTS_DESCRIPTION = [
        MediaUploadedEvent::class => 'Fires when a media file is uploaded',
        AppActivatedEvent::class => 'Fires when an app is activated',
        AppDeactivatedEvent::class => 'Fires when an app is deactivated',
        AppDeletedEvent::class => 'Fires when an app is deleted',
        AppInstalledEvent::class => 'Fires when an app is installed',
        AppUpdatedEvent::class => 'Fires when an app is updated',
        AppPermissionsUpdated::class => 'Fires when an apps permissions were updated with a list of the currently accepted permissions, eg after new were accepted or revoked',
        UpdatePostFinishEvent::class => 'Fires after an shopware update has been finished',
        SystemConfigChangedHook::class => 'Fires when a system config value is changed',
        SystemHeartbeatEvent::class => 'Fires as a recurrent task. Indicates to the app that the system is up and running.',
        WebhookActivatedEvent::class => 'Fires when one of the app\'s webhooks recovers to healthy',
        WebhookDegradedEvent::class => 'Fires when one of the app\'s webhooks degrades after repeated transient delivery failures',
        WebhookSuspendedEvent::class => 'Fires when one of the app\'s webhooks is suspended and new events start being shed',
        WebhookDisabledEvent::class => 'Fires when one of the app\'s webhooks is disabled by escalation or an operator',
    ];

    public const HOOKABLE_EVENTS_PRIVILEGES = [
        MediaUploadedEvent::class => ['media:read'],
        AppActivatedEvent::class => [],
        AppDeactivatedEvent::class => [],
        AppDeletedEvent::class => [],
        AppInstalledEvent::class => [],
        AppUpdatedEvent::class => [],
        AppPermissionsUpdated::class => [],
        UpdatePostFinishEvent::class => [],
        SystemConfigChangedHook::class => ['system_config:read'],
        SystemHeartbeatEvent::class => [],
        WebhookActivatedEvent::class => [],
        WebhookDegradedEvent::class => [],
        WebhookSuspendedEvent::class => [],
        WebhookDisabledEvent::class => [],
    ];

    public function getName(): string;

    /**
     * @return array<mixed>
     */
    public function getWebhookPayload(?AppEntity $app = null): array;

    /**
     * returns if it is allowed to dispatch the event to given app with given permissions
     */
    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool;
}
