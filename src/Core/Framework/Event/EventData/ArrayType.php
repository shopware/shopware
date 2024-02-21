<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class ArrayType implements EventDataType
{
    final public const TYPE = 'array';

    public function __construct(private readonly EventDataType $type)
    {
    }

    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'of' => $this->type->toArray(),
        ];
    }
}
