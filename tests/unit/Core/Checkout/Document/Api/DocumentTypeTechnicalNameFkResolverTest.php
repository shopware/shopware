<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Api\DocumentTypeTechnicalNameFkResolver;
use Shopware\Core\Framework\Api\Sync\FkReference;

/**
 * @internal
 */
#[CoversClass(DocumentTypeTechnicalNameFkResolver::class)]
class DocumentTypeTechnicalNameFkResolverTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('document_type.technical_name', DocumentTypeTechnicalNameFkResolver::getName());
    }

    public function testResolve(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'invoice' => 'invoice0000000000000000000000001',
                'credit_note' => 'creditnote000000000000000000000002',
            ]);

        $resolver = new DocumentTypeTechnicalNameFkResolver($connection);

        $references = [
            new FkReference('ops/0/documentTypeId', 'document_type', 'technicalName', 'invoice', false),
            new FkReference('ops/1/documentTypeId', 'document_type', 'technicalName', 'credit_note', false),
            new FkReference('ops/2/documentTypeId', 'document_type', 'technicalName', 'unknown', false),
        ];

        $result = $resolver->resolve($references);

        static::assertSame('invoice0000000000000000000000001', $result[0]->resolved);
        static::assertSame('creditnote000000000000000000000002', $result[1]->resolved);
        static::assertNull($result[2]->resolved);
    }

    public function testResolveWithEmptyInputDoesNotQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $resolver = new DocumentTypeTechnicalNameFkResolver($connection);

        static::assertSame([], $resolver->resolve([]));
    }
}
