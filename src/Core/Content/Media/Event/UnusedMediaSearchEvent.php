<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
class UnusedMediaSearchEvent extends Event implements ShopwareEvent
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        private array $ids,
        private readonly ?Context $context = null
    ) {
        if ($context === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Not passing $context to ' . static::class . ' is deprecated and will be required in v6.8.0.');
        }
    }

    public function getContext(): Context
    {
        // tag:v6.8.0 - Remove this null check, $context will be required
        if ($this->context === null) {
            throw MediaException::invalidEventData('No context provided. Pass $context to the constructor of ' . static::class);
        }

        return $this->context;
    }

    /**
     * @deprecated tag:v6.8.0 - Use getContext() instead, $context will be required in the constructor.
     */
    public function getNullableContext(): ?Context
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'getNullableContext() is deprecated, use getContext() instead.');

        return $this->context;
    }

    /**
     * Specify that some IDs should NOT be deleted, they are in fact used.
     *
     * @param array<string> $ids
     */
    public function markAsUsed(array $ids): void
    {
        $this->ids = array_values(array_diff($this->ids, $ids));
    }

    /**
     * @return list<string> $ids
     */
    public function getUnusedIds(): array
    {
        return $this->ids;
    }
}
