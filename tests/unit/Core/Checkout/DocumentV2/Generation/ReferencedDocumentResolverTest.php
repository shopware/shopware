<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Generation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\ReferencedDocumentResolver;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Doctrine\FakeQueryBuilder;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ReferencedDocumentResolver::class)]
class ReferencedDocumentResolverTest extends TestCase
{
    private string $orderId;

    protected function setUp(): void
    {
        $this->orderId = Uuid::randomHex();
    }

    public function testResolvesTheOrdersInvoiceWhenNoExplicitReferenceIsGiven(): void
    {
        $documentId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();

        $resolver = $this->createResolver([
            $this->invoiceRow($documentId, $orderVersionId, documentNumber: '1000'),
        ]);

        $reference = $resolver->resolve($this->orderId, null);

        static::assertSame($documentId, $reference->id);
        static::assertSame('1000', $reference->documentNumber);
        static::assertSame($orderVersionId, $reference->orderVersionId);
    }

    public function testResolvesTheDocumentNumberFromConfigWhenColumnIsEmpty(): void
    {
        $resolver = $this->createResolver([
            $this->invoiceRow(
                Uuid::randomHex(),
                Uuid::randomHex(),
                documentNumber: '',
                config: '{"documentNumber":"1000"}',
            ),
        ]);

        $reference = $resolver->resolve($this->orderId, null);

        static::assertSame('1000', $reference->documentNumber);
    }

    public function testThrowsWhenNoReferenceInvoiceExists(): void
    {
        $resolver = $this->createResolver([]);

        $this->expectExceptionObject(DocumentV2Exception::referencedInvoiceNotFound($this->orderId));

        $resolver->resolve($this->orderId, null);
    }

    public function testThrowsWhenTheExplicitReferenceIsNotAValidUuid(): void
    {
        $resolver = $this->createResolver([
            $this->invoiceRow(Uuid::randomHex(), Uuid::randomHex(), documentNumber: '1000'),
        ]);

        $this->expectExceptionObject(DocumentV2Exception::referencedInvoiceNotFound($this->orderId));

        $resolver->resolve($this->orderId, 'not-a-uuid');
    }

    public function testThrowsWhenTheReferencedOrderVersionIsUnresolvable(): void
    {
        $storedOrderVersionId = Uuid::randomHex();

        $resolver = $this->createResolver(
            [
                $this->invoiceRow(
                    Uuid::randomHex(),
                    $storedOrderVersionId,
                    documentNumber: '1000',
                    orderRowVersionId: Uuid::randomHex(),
                ),
            ],
            $storedOrderVersionId,
        );

        $this->expectExceptionObject(DocumentV2Exception::referencedOrderVersionNotFound($this->orderId));

        $resolver->resolve($this->orderId, null);
    }

    public function testResolvesAStaticDocumentStoredAtTheLiveVersion(): void
    {
        $documentId = Uuid::randomHex();

        $resolver = $this->createResolver(
            [
                $this->invoiceRow(
                    $documentId,
                    Defaults::LIVE_VERSION,
                    documentNumber: '1000',
                    orderRowVersionId: Defaults::LIVE_VERSION,
                ),
            ],
            Defaults::LIVE_VERSION,
        );

        $reference = $resolver->resolve($this->orderId, null);

        static::assertSame($documentId, $reference->id);
        static::assertSame(Defaults::LIVE_VERSION, $reference->orderVersionId);
    }

    public function testThrowsWhenTheDocumentRowVanishesBeforeTheStoredVersionRecheck(): void
    {
        $resolver = $this->createResolver(
            [
                $this->invoiceRow(
                    Uuid::randomHex(),
                    Defaults::LIVE_VERSION,
                    documentNumber: '1000',
                    orderRowVersionId: Defaults::LIVE_VERSION,
                ),
            ],
            storedOrderVersionId: null,
        );

        $this->expectExceptionObject(DocumentV2Exception::referencedOrderVersionNotFound($this->orderId));

        $resolver->resolve($this->orderId, null);
    }

    public function testThrowsWhenTheReferencedInvoiceHasNoNumber(): void
    {
        $resolver = $this->createResolver([
            $this->invoiceRow(Uuid::randomHex(), Uuid::randomHex(), documentNumber: '', config: '{}'),
        ]);

        $this->expectExceptionObject(DocumentV2Exception::referencedInvoiceNumberMissing($this->orderId));

        $resolver->resolve($this->orderId, null);
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function createResolver(array $rows, ?string $storedOrderVersionId = null): ReferencedDocumentResolver
    {
        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn(new FakeQueryBuilder($connection, $rows));
        $connection->method('fetchOne')->willReturn(
            $storedOrderVersionId === null ? false : Uuid::fromHexToBytes($storedOrderVersionId),
        );

        return new ReferencedDocumentResolver(new ReferenceInvoiceLoader($connection), $connection);
    }

    /**
     * @return array<string, string>
     */
    private function invoiceRow(
        string $documentId,
        string $orderVersionId,
        string $documentNumber,
        string $config = '{}',
        ?string $orderRowVersionId = null,
    ): array {
        return [
            'id' => $documentId,
            'orderId' => $this->orderId,
            'orderVersionId' => $orderVersionId,
            'versionId' => $orderRowVersionId ?? $orderVersionId,
            'deepLinkCode' => '',
            'config' => $config,
            'documentNumber' => $documentNumber,
        ];
    }
}
