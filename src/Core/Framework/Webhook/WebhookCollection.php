<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @method void                      add(WebhookEntity $entity)
 * @method void                      set(string $key, WebhookEntity $entity)
 * @method \Generator<WebhookEntity> getIterator()
 * @method array<WebhookEntity>      getElements()
 * @method WebhookEntity|null        get(string $key)
 * @method WebhookEntity|null        first()
 * @method WebhookEntity|null        last()
 */
class WebhookCollection extends EntityCollection
{
    public function filterForEvent(string $name)
    {
        return $this->filterByProperty('eventName', $name);
    }

    /**
     * @return string[]
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
