<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Service\ReferenceInvoiceLoader;
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
    #[DataProvider('invoicesDataProvider')]
    public function testInvoiceLoader(string $orderVersionId, string $versionId, string $invoiceOrderVersionId): void
    {
        $orderId = Uuid::randomHex();
        $deepLinkCode = 'uojRco91RO5hZ1l6VihVDjKZpWydHVqb';
        $referenceDocumentId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn(
            new FakeQueryBuilder($connection, [[
                'id' => Uuid::randomHex(),
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'versionId' => $versionId,
                'deepLinkCode' => $deepLinkCode,
                'config' => '{}',
            ]]),
        );

        $referenceInvoiceLoader = new ReferenceInvoiceLoader($connection);
        $invoice = $referenceInvoiceLoader->load($orderId, $referenceDocumentId, $deepLinkCode);

        static::assertNotSame(Defaults::LIVE_VERSION, $versionId);
        static::assertSame($invoiceOrderVersionId, $invoice['orderVersionId']);
    }

    /**
     * @return iterable<string, array{orderVersionId: string, versionId: string, invoiceOrderVersionId: string}>
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

    public function testResolveReferencedInvoiceReturnsIdAndNumber(): void
    {
        $id = Uuid::randomHex();
        $loader = $this->createLoader([$this->invoiceRow($id, documentNumber: '1000')]);

        $result = $loader->resolveReferencedInvoice(Uuid::randomHex(), null);

        static::assertSame($id, $result['id']);
        static::assertSame('1000', $result['documentNumber']);
    }

    public function testResolveReferencedInvoiceFallsBackToTheNumberStoredInConfig(): void
    {
        $loader = $this->createLoader([
            $this->invoiceRow(Uuid::randomHex(), documentNumber: '', config: '{"documentNumber":"2000"}'),
        ]);

        $result = $loader->resolveReferencedInvoice(Uuid::randomHex(), null);

        static::assertSame('2000', $result['documentNumber']);
    }

    public function testResolveReferencedInvoiceThrowsWhenNoInvoiceExists(): void
    {
        $orderId = Uuid::randomHex();
        $loader = $this->createLoader([]);

        $this->expectExceptionObject(DocumentV2Exception::referencedInvoiceNotFound($orderId));

        $loader->resolveReferencedInvoice($orderId, null);
    }

    public function testResolveReferencedInvoiceThrowsWhenTheInvoiceHasNoNumber(): void
    {
        $orderId = Uuid::randomHex();
        $loader = $this->createLoader([$this->invoiceRow(Uuid::randomHex(), documentNumber: '', config: '{}')]);

        $this->expectExceptionObject(DocumentV2Exception::referencedInvoiceNumberMissing($orderId));

        $loader->resolveReferencedInvoice($orderId, null);
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function createLoader(array $rows): ReferenceInvoiceLoader
    {
        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn(new FakeQueryBuilder($connection, $rows));

        return new ReferenceInvoiceLoader($connection);
    }

    /**
     * @return array<string, string>
     */
    private function invoiceRow(string $id, string $documentNumber, string $config = '{}'): array
    {
        $versionId = Uuid::randomHex();

        return [
            'id' => $id,
            'orderId' => Uuid::randomHex(),
            'orderVersionId' => $versionId,
            'versionId' => $versionId,
            'deepLinkCode' => '',
            'config' => $config,
            'documentNumber' => $documentNumber,
        ];
    }
}
