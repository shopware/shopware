<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

class ScalarValueType implements EventDataType
{
    public const TYPE_STRING = 'string';
    public const TYPE_INT = 'int';
    public const TYPE_FLOAT = 'float';
    public const TYPE_BOOL = 'bool';

    public const VALID_TYPES = [
        self::TYPE_STRING,
        self::TYPE_INT,
        self::TYPE_FLOAT,
        self::TYPE_BOOL,
    ];

    /**
     * @var string
     */
    private $type;

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
