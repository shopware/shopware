<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

class ArrayType implements EventDataType
{
    /**
     * @var EventDataType
     */
    private $type;

    public function __construct(EventDataType $type)
    {
        $this->type = $type;
    }

    public function toArray(): array
    {
        return [
            'type' => 'array',
            'of' => $this->type->toArray(),
        ];
    }
}
