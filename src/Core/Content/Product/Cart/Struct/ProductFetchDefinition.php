<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ProductFetchDefinition extends Struct
{
    /**
     * @var string[]
     */
    protected $ids;

    /**
     * @param string[] $ids
     */
    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    /**
     * @return string[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
