<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class ObjectType extends AbstractEventDataType
{
    final public const TYPE = 'object';

    /**
     * @var array<string, EventDataType>
     */
    private array $data = [];

    public function add(string $name, EventDataType $type): self
    {
        $this->data[$name] = $type;

        return $this;
    }

    public function get(string $name): ?EventDataType
    {
        return $this->data[$name] ?? null;
    }

    /**
     * @return array<string, EventDataType>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'type' => self::TYPE,
            'data' => \array_map(fn (EventDataType $type) => $type->toArray(), $this->data),
        ];
    }
}
