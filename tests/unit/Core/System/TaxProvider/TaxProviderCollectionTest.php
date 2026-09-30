<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\TaxProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\TaxProvider\TaxProviderCollection;
use Shopware\Core\System\TaxProvider\TaxProviderEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TaxProviderCollection::class)]
class TaxProviderCollectionTest extends TestCase
{
    public function testSortByPriorityOrdersHighestFirst(): void
    {
        $collection = new TaxProviderCollection([
            $this->provider('low', 10),
            $this->provider('high', 100),
            $this->provider('mid', 50),
        ]);

        $collection->sortByPriority();

        static::assertSame(['high', 'mid', 'low'], array_keys($collection->getElements()));
    }

    public function testApiAlias(): void
    {
        static::assertSame('tax_provider_collection', (new TaxProviderCollection())->getApiAlias());
    }

    private function provider(string $id, int $priority): TaxProviderEntity
    {
        $provider = new TaxProviderEntity();
        $provider->setUniqueIdentifier($id);
        $provider->setPriority($priority);

        return $provider;
    }
}
