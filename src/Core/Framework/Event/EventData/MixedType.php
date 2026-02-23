<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class MixedType extends AbstractEventDataType
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
