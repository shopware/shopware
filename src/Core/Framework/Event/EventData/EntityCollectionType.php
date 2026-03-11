<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class EntityCollectionType extends AbstractEventDataType
{
    final public const TYPE = 'collection';

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    public function __construct(private readonly string $definitionClass)
    {
    }

    /**
     * @return class-string<EntityDefinition>
     */
    public function getDefinitionClass(): string
    {
        return $this->definitionClass;
    }

    /**
     * @return array{nullable:bool, type: string, entityClass: class-string<EntityDefinition>}
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => self::TYPE,
            'entityClass' => $this->definitionClass,
        ];
    }
}
