<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class EntityCollectionType implements EventDataType
{
    /**
     * @var string|EntityDefinition
     */
    private $definition;

    public function __construct(string $definition)
    {
        $this->definition = $definition;
    }

    public function toArray(): array
    {
        return [
            'type' => 'collection',
            'entity' => $this->definition::getEntityName(),
        ];
    }
}
