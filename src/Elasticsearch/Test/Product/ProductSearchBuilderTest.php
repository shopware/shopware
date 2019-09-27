<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Product;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Product\ProductSearchBuilder;
use Symfony\Component\HttpFoundation\Request;

class ProductSearchBuilderTest extends TestCase
{
    public function testArraySearchTerm(): void
    {
        $mockProductSearchBuilder = $this->getMockBuilder(ProductSearchBuilder::class)->disableOriginalConstructor()->getMock();
        $mockProductSearchBuilder->method('build')->willThrowException(new \Exception('Should not be called'));

        $mockElasticsearchHelper = $this->getMockBuilder(ElasticsearchHelper::class)->disableOriginalConstructor()->getMock();
        $mockElasticsearchHelper->method('allowSearch')->willReturn(true);

        $searchBuilder = new ProductSearchBuilder(
            $mockProductSearchBuilder,
            $mockElasticsearchHelper,
            new ProductDefinition()
        );

        $mockSalesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $mockSalesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $request = new Request();

        // /search?search[]=Word&search[]=Word
        $request->query->set('search', [
            'Word 1',
            'Word 2',
        ]);

        $searchBuilder->build($request, $criteria, $mockSalesChannelContext);

        static::assertNotEquals('array', $criteria->getTerm());
        static::assertEquals('Word 1 Word 2', $criteria->getTerm());
    }
}
