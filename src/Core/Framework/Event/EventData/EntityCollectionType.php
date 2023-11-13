<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class EntityCollectionType implements EventDataType
{
    final public const TYPE = 'collection';

    public function __construct(private readonly string $definitionClass)
    {
    }

    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'entityClass' => $this->definitionClass,
        ];
    }
}
