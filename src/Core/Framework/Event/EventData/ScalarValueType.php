<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class ScalarValueType implements EventDataType
{
    final public const TYPE_STRING = 'string';
    final public const TYPE_INT = 'int';
    final public const TYPE_FLOAT = 'float';
    final public const TYPE_BOOL = 'bool';

    final public const VALID_TYPES = [
        self::TYPE_STRING,
        self::TYPE_INT,
        self::TYPE_FLOAT,
        self::TYPE_BOOL,
    ];

    private readonly string $type;

    public function __construct(string $type)
    {
        if (!\in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid type "%s" provided, valid ones are: %s', $type, implode(', ', self::VALID_TYPES)));
        }

        $this->type = $type;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
        ];
    }
}
