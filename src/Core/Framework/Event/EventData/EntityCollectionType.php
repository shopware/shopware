<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

class EntityCollectionType implements EventDataType
{
    /**
     * @var string
     */
    private $definitionClass;

    public function __construct(string $definitionClass)
    {
        $this->definitionClass = $definitionClass;
    }

    public function toArray(): array
    {
        return [
            'type' => 'collection',
            'entityClass' => $this->definitionClass,
        ];
    }
}
