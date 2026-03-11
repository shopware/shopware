<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class ArrayType extends AbstractEventDataType
{
    final public const TYPE = 'array';

    public function __construct(private readonly EventDataType $type)
    {
    }

    public function getType(): EventDataType
    {
        return $this->type;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => self::TYPE,
            'of' => $this->type->toArray(),
        ];
    }
}
