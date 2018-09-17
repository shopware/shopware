<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ProductServiceFetchDefinition extends Struct
{
    /**
     * @var string[]
     */
    protected $serviceIds;

    /**
     * @param string[] $serviceIds
     */
    public function __construct(array $serviceIds)
    {
        $this->serviceIds = $serviceIds;
    }

    /**
     * @return string[]
     */
    public function getServiceIds(): array
    {
        return $this->serviceIds;
    }
}
