<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

class EntityCollectionType implements EventDataType
{
    public const TYPE = 'collection';

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
            'type' => self::TYPE,
            'entityClass' => $this->definitionClass,
        ];
    }
}
