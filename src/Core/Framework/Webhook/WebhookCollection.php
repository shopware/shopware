<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

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

    protected function getExpectedClass(): string
    {
        return WebhookEntity::class;
    }
}
