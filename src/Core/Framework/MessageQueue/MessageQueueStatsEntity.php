<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Feature;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - use `shopware.increment.message_queue.gateway` service instead
 */
class MessageQueueStatsEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $size;

    public function getId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '`shopware.increment.message_queue.gateway`')
        );

        return $this->id;
    }

    public function setId(string $id): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '`shopware.increment.message_queue.gateway`')
        );

        $this->id = $id;
        $this->_uniqueIdentifier = $id;
    }

    public function getName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '`shopware.increment.message_queue.gateway`')
        );

        return $this->name;
    }

    public function setName(string $name): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '`shopware.increment.message_queue.gateway`')
        );

        $this->name = $name;
    }

    public function getSize(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '`shopware.increment.message_queue.gateway`')
        );

        return $this->size;
    }

    public function setSize(int $size): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', '`shopware.increment.message_queue.gateway`')
        );

        $this->size = $size;
    }
}
