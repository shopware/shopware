<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SearchKeyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilder;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductSearchBuilder::class)]
class ProductSearchBuilderTest extends TestCase
{
    public function testSearchTermMaxLengthReached(): void
    {
        $termInterpreter = $this->createMock(ProductSearchTermInterpreterInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $searchBuilder = new ProductSearchBuilder(
            $termInterpreter,
            $logger,
            20
        );

        $mockSalesChannelContext = $this->createMock(SalesChannelContext::class);
        $mockSalesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $request = new Request();

        $request->query->set('search', 'This search term\'s length is over 20 characters');

        $logger
            ->expects(static::once())
            ->method('notice')
            ->with(
                'The search term "{term}" was trimmed because it exceeded the maximum length of {maxLength} characters.',
                [
                    'term' => 'This search term\'s length is over 20 characters',
                    'maxLength' => 20,
                ]
            );
        $termInterpreter->expects(static::once())
            ->method('interpret')
            ->with('This search term\'s l', static::isInstanceOf(Context::class));
        $searchBuilder->build($request, $criteria, $mockSalesChannelContext);
    }
}
