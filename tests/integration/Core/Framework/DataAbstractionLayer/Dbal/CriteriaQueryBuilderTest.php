<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\ResolveCriteriaProductListingRoute;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class CriteriaQueryBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;
    private const VALID_TEST_SET = [
        's1' => '0198bd43b1c37964a5c1ecbd2d89fd6e',
        'p1' => '0198bd4417f471a69742ca2390243653',
        'p1.1' => '0198bd446e0973448206e3197b2d24ea',
        'p1.2' => '0198bd446e077084984f95175b2bea27',
    ];

    private const INVALID_TEST_SET = [
        's1' => '00000000000000000000000000000001',
        'p1' => '00000000000000000000000000000002',
        'p1.1' => '00000000000000000000000000000003',
        'p1.2' => '00000000000000000000000000000004',
    ];

    public IdsCollection $ids;

    public function testSortingByCheapestPrice(): void
    {
        $idSet = self::VALID_TEST_SET;

        $this->ids = new IdsCollection();
        $this->createExampleProducts($idSet);

        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            'anytokenstring',
            TestDefaults::SALES_CHANNEL
        );

        $result = static::getContainer()->get(ResolveCriteriaProductListingRoute::class)->load(
            $this->ids->get('test-category'),
            new Request(query: ['order' => 'price-asc']),
            $context,
            new Criteria()
        );

        $actualIds = $result->getResult()->getEntities()->getIds();
        static::assertSame(
            [
                $idSet['p1'],
                $idSet['s1'],
            ],
            array_values($actualIds)
        );
    }

    private function createExampleProducts(array $idSet): void
    {
        $this->ids->set('s1', $idSet['s1']);
        $this->ids->set('p1', $idSet['p1']);
        $this->ids->set('p1.1', $idSet['p1.1']);
        $this->ids->set('p1.2', $idSet['p1.2']);

        $s1 = (new ProductBuilder($this->ids, 's1'))
            ->price(100)
            ->category('test-category')
            ->visibility()
            ->build();
        $p1 = (new ProductBuilder($this->ids, 'p1'))
            ->price(100)
            ->category('test-category')
            ->variantListingConfig(['displayParent' => true])
            ->visibility()
            ->variant(
                (new ProductBuilder($this->ids, 'p1.1'))
                    ->price(50)
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'p1.2'))
                    ->price(200)
                    ->build()
            )
            ->build();

        static::getContainer()->get('product.repository')->create([$s1, $p1], Context::createDefaultContext());
    }
}
