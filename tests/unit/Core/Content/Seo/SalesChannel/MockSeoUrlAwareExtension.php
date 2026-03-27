<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SalesChannel;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
class MockSeoUrlAwareExtension extends Struct
{
    /**
     * @var list<SalesChannelProductEntity>
     */
    protected array $searchResults = [];

    public function addSearchResult(SalesChannelProductEntity $entity): void
    {
        $this->searchResults[] = $entity;
    }
}
