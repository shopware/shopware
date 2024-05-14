<?php declare(strict_types=1);

namespace Shopware\Core\System\Tag\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class FilteredTagIdsStruct extends Struct
{
    /**
     * @param array<string> $ids
     */
    public function __construct(
        protected array $ids,
        protected int $total
    ) {
    }

    /**
     * @return array<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
