<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @psalm-suppress MoreSpecificImplementedParamType
 *
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
    protected function getExpectedClass(): string
    {
        return WebhookEntity::class;
    }
}
