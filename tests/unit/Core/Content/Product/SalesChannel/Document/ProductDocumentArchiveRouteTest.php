<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Product\Extension\ProductDocumentArchiveRouteExtension;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentArchive;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentArchiver;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentArchiveRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentArchiveRoute::class)]
class ProductDocumentArchiveRouteTest extends TestCase
{
    private SalesChannelContext $context;

    private SalesChannelProductEntity $product;

    protected function setUp(): void
    {
        $this->context = Generator::generateSalesChannelContext();
        $this->product = new SalesChannelProductEntity();
        $this->product->setId(Uuid::randomHex());
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $response = new Response();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ProductDocumentArchiveRouteExtension::NAME . '.pre', function (ProductDocumentArchiveRouteExtension $extension) use ($request, $response): void {
            static::assertSame(
                ['productId' => $this->product->getId(), 'request' => $request, 'salesChannelContext' => $this->context],
                $extension->getParams()
            );

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = $this->createRoute(extensions: new ExtensionDispatcher($dispatcher));

        static::assertSame($response, $route->load($this->product->getId(), $request, $this->context));
    }

    #[DataProvider('fileNames')]
    public function testStreamsTheStoredArchive(string $fileName, string $disposition): void
    {
        $archive = new ProductDocumentArchive('product-documents/archives/archive.zip', $fileName, 3);
        $archiver = static::createStub(ProductDocumentArchiver::class);
        $archiver->method('archive')->willReturn($archive);
        $archiver->method('readStream')->willReturnCallback(static function () {
            $stream = fopen('php://memory', 'r+');
            static::assertIsResource($stream);
            fwrite($stream, 'zip');
            rewind($stream);

            return $stream;
        });

        $response = $this->createRoute(archiver: $archiver)->load($this->product->getId(), new Request(), $this->context);

        static::assertInstanceOf(StreamedResponse::class, $response);
        static::assertSame('application/zip', $response->headers->get('Content-Type'));
        static::assertSame('3', $response->headers->get('Content-Length'));
        static::assertSame($disposition, $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        static::assertSame('zip', ob_get_clean());
    }

    public static function fileNames(): \Generator
    {
        yield 'an ASCII name is sent as it is' => ['Cordless drill.zip', 'attachment; filename="Cordless drill.zip"'];
        yield 'other characters get an ASCII fallback' => ['Bohrer für Holz.zip', 'attachment; filename="Bohrer f_r Holz.zip"; filename*=utf-8\'\'Bohrer%20f%C3%BCr%20Holz.zip'];
    }

    #[DataProvider('closeoutSettings')]
    public function testLoadsOnlyProductsVisibleInTheSalesChannel(bool $hideCloseout, int $filterCount): void
    {
        $productRepository = new StaticSalesChannelRepository([
            function (Criteria $criteria) use ($filterCount): SalesChannelProductCollection {
                static::assertSame([$this->product->getId()], $criteria->getIds());
                static::assertCount($filterCount, $criteria->getFilters());
                static::assertInstanceOf(ProductAvailableFilter::class, $criteria->getFilters()[0]);

                return new SalesChannelProductCollection();
            },
        ]);

        $this->expectExceptionObject(ProductException::productNotFound($this->product->getId()));

        $this->createRoute(productRepository: $productRepository, hideCloseout: $hideCloseout)
            ->load($this->product->getId(), new Request(), $this->context);
    }

    public static function closeoutSettings(): \Generator
    {
        yield 'closeout products are shown' => [false, 1];
        yield 'closeout products out of stock are hidden' => [true, 2];
    }

    public function testAProductWithoutDocumentsHasNoArchive(): void
    {
        $this->expectExceptionObject(ProductException::documentArchiveNotFound($this->product->getId()));

        $this->createRoute()->load($this->product->getId(), new Request(), $this->context);
    }

    public function testRejectsTooManyDownloadsFromOneIpAddressAndLogsThem(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::PRODUCT_DOCUMENTS_ARCHIVE, '192.0.2.1')
            ->willThrowException(new RateLimitExceededException(time() + 30));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('notice')
            ->with(static::anything(), ['productId' => $this->product->getId(), 'waitTime' => 30]);

        $this->expectExceptionObject(ProductException::documentArchiveThrottled(30));

        $this->createRoute(rateLimiter: $rateLimiter, logger: $logger)
            ->load($this->product->getId(), new Request(server: ['REMOTE_ADDR' => '192.0.2.1']), $this->context);
    }

    /**
     * @param StaticSalesChannelRepository<SalesChannelProductCollection>|null $productRepository
     */
    private function createRoute(
        ?StaticSalesChannelRepository $productRepository = null,
        ?ProductDocumentArchiver $archiver = null,
        ?RateLimiter $rateLimiter = null,
        bool $hideCloseout = false,
        ?ExtensionDispatcher $extensions = null,
        ?LoggerInterface $logger = null,
    ): ProductDocumentArchiveRoute {
        $closeoutFilterFactory = static::createStub(AbstractProductCloseoutFilterFactory::class);
        $closeoutFilterFactory->method('create')->willReturn(new MultiFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('product.isCloseout', false)]));

        return new ProductDocumentArchiveRoute(
            $productRepository ?? new StaticSalesChannelRepository([new SalesChannelProductCollection([$this->product])]),
            $archiver ?? static::createStub(ProductDocumentArchiver::class),
            $rateLimiter ?? static::createStub(RateLimiter::class),
            new StaticSystemConfigService(['core.listing.hideCloseoutProductsWhenOutOfStock' => $hideCloseout]),
            $closeoutFilterFactory,
            $extensions ?? new ExtensionDispatcher(new EventDispatcher()),
            $logger ?? new NullLogger(),
        );
    }
}
