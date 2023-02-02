<?php declare(strict_types=1);

namespace Shopware\Core\System\Tag\Struct;

use Shopware\Core\Framework\Struct\Struct;

class FilteredTagIdsStruct extends Struct
{
    protected array $ids;

    protected int $total;

    public function __construct(array $ids, int $total)
    {
        $this->ids = $ids;
        $this->total = $total;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
