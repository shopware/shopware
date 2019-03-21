<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class EntityType implements EventDataType
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
            'type' => 'entity',
            'entity' => $this->definition::getEntityName(),
        ];
    }
}
