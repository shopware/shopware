<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Doctrine\FakeQueryBuilder;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ReferenceInvoiceLoader::class)]
class ReferenceInvoiceLoaderTest extends TestCase
{
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }

    #[DataProvider('invoicesDataProvider')]
    public function testInvoiceLoader(string $orderVersionId, string $versionId, string $invoiceOrderVersionId): void
    {
        $orderId = Uuid::randomHex();
        $deepLinkCode = 'uojRco91RO5hZ1l6VihVDjKZpWydHVqb';
        $referenceDocumentId = Uuid::randomHex();

        $this->connection->expects(static::once())->method('createQueryBuilder')->willReturn(
            new FakeQueryBuilder($this->connection, [[
                'id' => Uuid::randomHex(),
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'versionId' => $versionId,
                'deepLinkCode' => $deepLinkCode,
                'config' => '{}',
            ]]),
        );

        $referenceInvoiceLoader = new ReferenceInvoiceLoader($this->connection);
        $invoice = $referenceInvoiceLoader->load($orderId, $referenceDocumentId, $deepLinkCode);

        static::assertNotSame(Defaults::LIVE_VERSION, $versionId);
        static::assertSame($invoiceOrderVersionId, $invoice['orderVersionId']);
    }

    /**
     * @return array<string, array{orderVersionId: string, versionId: string, invoiceOrderVersionId: string}>
     */
    public static function invoicesDataProvider(): iterable
    {
        $versionId = Uuid::randomHex();

        yield 'load invoice with live version id' => [
            'orderVersionId' => Uuid::randomHex(),
            'versionId' => $versionId,
            'invoiceOrderVersionId' => Defaults::LIVE_VERSION,
        ];

        yield 'load invoice with new version id' => [
            'orderVersionId' => $versionId,
            'versionId' => $versionId,
            'invoiceOrderVersionId' => $versionId,
        ];
    }
}
