<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

use Shopware\Core\Framework\Struct\Struct;

class ValueCountItem extends Struct
{
    /**
     * @var mixed
     */
    protected $key;

    /**
     * @var int
     */
    protected $count;

    public function __construct($key, int $count)
    {
        $this->key = $key;
        $this->count = $count;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
