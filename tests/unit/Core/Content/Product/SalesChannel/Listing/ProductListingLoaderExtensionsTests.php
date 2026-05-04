<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
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
        $responseBody = json_encode(['ids' => ['plugin-id'], 'total' => 1], \JSON_THROW_ON_ERROR);

        $mockHandler = new MockHandler([new Response(200, [], $responseBody)]);
        $handlerStack = HandlerStack::create($mockHandler);

        $history = [];
        $handlerStack->push(Middleware::history($history));

        $client = new Client(['handler' => $handlerStack]);

        $example = new ResolveListingIdsExample($client);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ResolveListingIdsExtension::NAME . '.pre', $example);

        $extension = new ResolveListingIdsExtension(
            new Criteria(),
            $this->createMock(SalesChannelContext::class)
        );

        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ResolveListingIdsExtension::NAME,
            extension: $extension,
            function: static function () {
                return IdSearchResult::fromIds(['core-id'], new Criteria(), Context::createDefaultContext());
            }
        );

        static::assertInstanceOf(IdSearchResult::class, $result);
        static::assertSame(['plugin-id'], $result->getIds());
        static::assertIsArray($history);
        static::assertCount(1, $history);

        $request = $history[0]['request'];
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('GET', $request->getMethod());
    }

    public function testResolveListingExtension(): void
    {
        $responseBody = json_encode(['ids' => ['plugin-id'], 'total' => 1], \JSON_THROW_ON_ERROR);

        $mockHandler = new MockHandler([new Response(200, [], $responseBody)]);
        $handlerStack = HandlerStack::create($mockHandler);

        $history = [];
        $handlerStack->push(Middleware::history($history));

        $client = new Client(['handler' => $handlerStack]);

        /** @var StaticEntityRepository<ProductCollection> $productRepo */
        $productRepo = new StaticEntityRepository([
            [(new ProductEntity())->assign(['id' => 'plugin-id'])],
        ]);
        $example = new ResolveListingExample($client, $productRepo);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($example);

        $extension = new ResolveListingExtension(
            new Criteria(),
            $this->createMock(SalesChannelContext::class),
        );

        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ResolveListingExtension::NAME,
            extension: $extension,
            function: static function () {
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
            }
        );

        static::assertInstanceOf(EntitySearchResult::class, $result);
        static::assertSame(['plugin-id'], array_values($result->getIds()));
        static::assertIsArray($history);
        static::assertCount(1, $history);

        $request = $history[0]['request'];
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('GET', $request->getMethod());
    }
}
