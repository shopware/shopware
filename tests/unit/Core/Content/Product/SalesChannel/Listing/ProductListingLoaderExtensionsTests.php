<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Extension\ResolveListingExtension;
use Shopware\Core\Content\Product\Extension\ResolveListingIdsExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Examples\ResolveListingExample;
use Shopware\Tests\Examples\ResolveListingIdsExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ResolveListingIdsExample::class)]
#[CoversClass(ResolveListingExample::class)]
#[CoversClass(ResolveListingExtension::class)]
#[CoversClass(ResolveListingIdsExtension::class)]
class ProductListingLoaderExtensionsTests extends TestCase
{
    public function testResolveListingIdsExtensions(): void
    {
        // @phpstan-ignore-next-line
        $client = $this->createMock(Client::class);
        $client->expects(static::once())
            ->method('get')
            ->willReturn(new Response(200, [], json_encode(['ids' => ['plugin-id'], 'total' => 1], \JSON_THROW_ON_ERROR)));

        $example = new ResolveListingIdsExample($client);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ResolveListingIdsExtension::pre(), $example);

        $extension = new ResolveListingIdsExtension(
            new Criteria(),
            $this->createMock(SalesChannelContext::class)
        );

        $result = (new ExtensionDispatcher($dispatcher))->publish($extension, function () {
            return IdSearchResult::fromIds(['core-id'], new Criteria(), Context::createDefaultContext());
        });

        static::assertInstanceOf(IdSearchResult::class, $result);

        static::assertEquals(['plugin-id'], $result->getIds());
    }

    public function testResolveListingExtension(): void
    {
        // @phpstan-ignore-next-line
        $client = $this->createMock(Client::class);
        $client->expects(static::once())
            ->method('get')
            ->willReturn(new Response(200, [], json_encode(['ids' => ['plugin-id'], 'total' => 1], \JSON_THROW_ON_ERROR)));

        $example = new ResolveListingExample(
            $client,
            new StaticEntityRepository([
                [(new ProductEntity())->assign(['id' => 'plugin-id'])],
            ]),
        );

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($example);

        $extension = new ResolveListingExtension(
            new Criteria(),
            $this->createMock(SalesChannelContext::class),
        );

        $result = (new ExtensionDispatcher($dispatcher))->publish($extension, function () {
            return new EntitySearchResult(
                'product',
                1,
                new ProductCollection([
                    (new ProductEntity())->assign(['id' => 'plugin-id']),
                ]),
                new AggregationResultCollection(),
                new Criteria(),
                Context::createDefaultContext()
            );
        });

        static::assertInstanceOf(EntitySearchResult::class, $result);

        static::assertEquals(['plugin-id'], array_values($result->getIds()));
    }
}
