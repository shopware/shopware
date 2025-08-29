<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
class AssociativeArrayType extends EventDataType
{
    final public const TYPE = 'associative_array';

    public function __construct(private readonly ScalarValueType $key, private readonly EventDataType $type)
    {
    }

    public function getKey(): ScalarValueType
    {
        return $this->key;
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
            'key' => $this->key->toArray(),
        ];
    }
}
