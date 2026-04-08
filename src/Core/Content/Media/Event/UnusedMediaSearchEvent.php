<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
class UnusedMediaSearchEvent extends Event
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

    public function getContext(): ?Context
    {
        if ($this->context === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Not passing $context to ' . static::class . ' is deprecated and will be required in v6.8.0.');
        }

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
