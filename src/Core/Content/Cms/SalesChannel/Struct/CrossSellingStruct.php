<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel\Struct;

use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('content')]
class CrossSellingStruct extends Struct
{
    /**
     * @var CrossSellingElementCollection|null
     */
    protected $crossSellings;

    public function getCrossSellings(): ?CrossSellingElementCollection
    {
        return $this->crossSellings;
    }

    public function setCrossSellings(CrossSellingElementCollection $crossSellings): void
    {
        $this->crossSellings = $crossSellings;
    }

    public function getApiAlias(): string
    {
        return 'cms_cross_selling';
    }
}
