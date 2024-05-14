<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
abstract class ManifestChangedEvent extends AppChangedEvent
{
    public const LIFECYCLE_EVENTS = [
        AppActivatedEvent::NAME,
        AppDeactivatedEvent::NAME,
        AppDeletedEvent::NAME,
        AppInstalledEvent::NAME,
        AppUpdatedEvent::NAME,
    ];

    public function __construct(
        AppEntity $app,
        private readonly Manifest $manifest,
        Context $context
    ) {
        parent::__construct($app, $context);
    }

    abstract public function getName(): string;

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'appVersion' => $this->manifest->getMetadata()->getVersion(),
        ];
    }
}
