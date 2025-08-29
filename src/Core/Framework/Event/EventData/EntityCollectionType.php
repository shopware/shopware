<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class EntityCollectionType extends EventDataType
{
    final public const TYPE = 'collection';

    public function __construct(private readonly string $definitionClass)
    {
    }

    public function getDefinitionClass(): string
    {
        return $this->definitionClass;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => self::TYPE,
            'entityClass' => $this->definitionClass,
        ];
    }
}
