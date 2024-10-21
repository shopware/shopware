<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Cart\ProductGateway;
use Shopware\Core\Content\Product\Events\ProductGatewayCriteriaEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ProductGateway::class)]
class ProductGatewayTest extends TestCase
{
    public function testSendCriteriaEvent(): void
    {
        $ids = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $context = Generator::createSalesChannelContext();

        $repository = $this->createMock(SalesChannelRepository::class);
        $emptySearchResult = new EntitySearchResult(
            'product',
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            $context->getContext()
        );
        $repository->method('search')->willReturn($emptySearchResult);

        $validator = static::callback(static fn ($subject) => $subject instanceof ProductGatewayCriteriaEvent);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(static::once())->method('dispatch')->with($validator);

        $gateway = new ProductGateway(
            $repository,
            $eventDispatcher
        );

        $gateway->get($ids, $context);
    }
}
