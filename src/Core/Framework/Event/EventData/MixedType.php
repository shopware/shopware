<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

class MixedType extends EventDataType
{
    final public const TYPE = 'mixed';

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => self::TYPE,
        ];
    }
}
