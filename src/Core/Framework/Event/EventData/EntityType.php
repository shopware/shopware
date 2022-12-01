<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class EntityType implements EventDataType
{
    public const TYPE = 'entity';

    /**
     * @var class-string<EntityDefinition>
     */
    private string $definitionClass;

    /**
     * @param class-string<EntityDefinition>|EntityDefinition $definitionClass
     */
    public function __construct(string|EntityDefinition $definitionClass)
    {
        if ($definitionClass instanceof EntityDefinition) {
            $definitionClass = $definitionClass::class;
        }

        $this->definitionClass = $definitionClass;
    }

    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'entityClass' => $this->definitionClass,
        ];
    }
}
