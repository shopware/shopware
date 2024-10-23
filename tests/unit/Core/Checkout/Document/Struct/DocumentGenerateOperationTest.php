<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DocumentGenerateOperation::class)]
class DocumentGenerateOperationTest extends TestCase
{
    public function testPublicAPI(): void
    {
        $ids = new IdsCollection();

        $operation = new DocumentGenerateOperation(
            $ids->get('order-id'),
            'xml',
            ['foo' => 'bar'],
            $ids->get('reference-id'),
            true,
            true,
        );

        static::assertSame($ids->get('order-id'), $operation->getOrderId());
        static::assertSame('xml', $operation->getFileType());
        static::assertSame(['foo' => 'bar'], $operation->getConfig());
        static::assertTrue($operation->isStatic());
        static::assertSame($ids->get('reference-id'), $operation->getReferencedDocumentId());
        static::assertTrue($operation->isPreview());
        static::assertNull($operation->getDocumentId());
        static::assertSame(Defaults::LIVE_VERSION, $operation->getOrderVersionId());

        $operation->setDocumentId($ids->get('document-id'));
        $operation->setOrderVersionId($ids->get('version-id'));
        $operation->setReferencedDocumentId($ids->get('new-reference-id'));

        static::assertSame($ids->get('document-id'), $operation->getDocumentId());
        static::assertSame($ids->get('version-id'), $operation->getOrderVersionId());
        static::assertSame($ids->get('new-reference-id'), $operation->getReferencedDocumentId());
    }
}
