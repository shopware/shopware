<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\LineItemDownloadLoader;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadEntity;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(LineItemDownloadLoader::class)]
class LineItemDownloadLoaderTest extends TestCase
{
    private MockObject&EntityRepository $productDownloadRepository;

    private LineItemDownloadLoader $loader;

    protected function setUp(): void
    {
        $this->productDownloadRepository = $this->createMock(EntityRepository::class);

        $this->loader = new LineItemDownloadLoader($this->productDownloadRepository);
    }

    public function testLineItemDoesNotExist(): void
    {
        $payload = $this->loader->load([], Context::createDefaultContext());

        static::assertEquals([], $payload);
    }

    public function testLineItemWithoutPayload(): void
    {
        $lineItems = [
            [
                'id' => Uuid::randomHex(),
            ],
        ];

        $payload = $this->loader->load($lineItems, Context::createDefaultContext());

        static::assertEquals([], $payload);
    }

    public function testNoPayloadContinue(): void
    {
        $productDownload = new ProductDownloadEntity();
        $productDownload->setId(Uuid::randomHex());
        $productDownload->setProductId(Uuid::randomHex());

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('getEntities')->willReturn(new EntityCollection([$productDownload]));
        $this->productDownloadRepository
            ->expects(static::once())
            ->method('search')
            ->willReturn($entitySearchResult);

        $lineItems = [
            [
                'id' => Uuid::randomHex(),
                'referencedId' => Uuid::randomHex(),
                'states' => [State::IS_DOWNLOAD],
            ],
        ];

        $payload = $this->loader->load($lineItems, Context::createDefaultContext());

        static::assertEquals([], $payload);
    }

    public function testLoadDownloadsPayload(): void
    {
        $productId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $productDownload = new ProductDownloadEntity();
        $productDownload->setId(Uuid::randomHex());
        $productDownload->setPosition(0);
        $productDownload->setProductId($productId);
        $productDownload->setMediaId($mediaId);
        $productDownload->setMedia(new MediaEntity());

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('getEntities')->willReturn(new EntityCollection([$productDownload]));
        $this->productDownloadRepository
            ->expects(static::once())
            ->method('search')
            ->willReturn($entitySearchResult);

        $lineItems = [
            [
                'id' => Uuid::randomHex(),
                'referencedId' => $productId,
                'states' => [State::IS_DOWNLOAD],
            ],
        ];

        $payload = $this->loader->load($lineItems, Context::createDefaultContext());

        static::assertEquals([
            [
                [
                    'position' => 0,
                    'mediaId' => $mediaId,
                    'accessGranted' => false,
                ],
            ],
        ], $payload);
    }
}
