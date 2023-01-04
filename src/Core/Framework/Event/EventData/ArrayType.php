<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class ArrayType implements EventDataType
{
    public const TYPE = 'array';

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
            'type' => self::TYPE,
            'of' => $this->type->toArray(),
        ];
    }
}
