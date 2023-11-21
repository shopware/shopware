<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\App\Event\ManifestChangedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @extends EntityCollection<WebhookEntity>
 */
#[Package('core')]
class WebhookCollection extends EntityCollection
{
    /**
     * @return WebhookCollection<WebhookEntity>
     */
    public function filterForEvent(string $name)
    {
        return $this->filterByProperty('eventName', $name);
    }

    /**
     * @return array<string>
     */
    public function getAclRoleIdsAsBinary(): array
    {
        return array_values($this->fmap(static function (WebhookEntity $webhook): ?string {
            if ($webhook->getApp()) {
                return Uuid::fromHexToBytes($webhook->getApp()->getAclRoleId());
            }

            return null;
        }));
    }

    /**
     * @return WebhookCollection<WebhookEntity>
     */
    public function allowedForDispatching(): self
    {
        return $this->filter(static function (WebhookEntity $webhook): bool {
            $app = $webhook->getApp();

            // if the webhook is not app based, it is always active
            if ($app === null) {
                return true;
            }

            // if the app is active, the webhook can be used
            if ($app->isActive()) {
                return true;
            }

            // we still need to dispatch lifecycle relevant webhooks, like app update even when the app is not active
            return \in_array(
                $webhook->getEventName(),
                ManifestChangedEvent::LIFECYCLE_EVENTS,
                true
            );
        });
    }

    protected function getExpectedClass(): string
    {
        return WebhookEntity::class;
    }
}
