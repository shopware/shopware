<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Cart\ProductGateway;
use Shopware\Core\Content\Product\Events\ProductGatewayCriteriaEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductGateway::class)]
class ProductGatewayTest extends TestCase
{
    public function testSendCriteriaEvent(): void
    {
        $ids = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $context = Generator::generateSalesChannelContext();

        $repository = static::createStub(SalesChannelRepository::class);
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
        $eventDispatcher->expects($this->once())->method('dispatch')->with($validator);

        $gateway = new ProductGateway(
            $repository,
            $eventDispatcher
        );

        $gateway->get($ids, $context);
    }

    public function testCriteriaContainsPayloadAssociations(): void
    {
        $ids = [Uuid::randomHex()];

        $context = Generator::generateSalesChannelContext();

        $repository = static::createStub(SalesChannelRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'product',
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            $context->getContext()
        ));

        $criteria = null;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')
            ->with(static::callback(static function (ProductGatewayCriteriaEvent $event) use (&$criteria) {
                $criteria = $event->getCriteria();

                return true;
            }));

        $gateway = new ProductGateway($repository, $eventDispatcher);
        $gateway->get($ids, $context);

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertTrue($criteria->hasAssociation('manufacturer'));
        static::assertTrue($criteria->hasAssociation('categories'));

        // the manufacturer name is read from the translated fields of the same query,
        // so no nested association may be added which would trigger a second read
        static::assertSame([], $criteria->getAssociation('manufacturer')->getAssociations());

        // the category breadcrumb is a stored field on the category itself, so the assigned
        // categories are enough and no ancestor association is needed
        static::assertSame([], $criteria->getAssociation('categories')->getAssociations());
    }
}
