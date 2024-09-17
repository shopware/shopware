<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stats\Entity;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class MessageTypeStatsEntity extends Struct
{
    protected string $type;

    protected int $count;

    public function __construct(string $type, int $count)
    {
        $this->type = $type;
        $this->count = $count;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
