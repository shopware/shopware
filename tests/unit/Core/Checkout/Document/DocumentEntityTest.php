<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentEntity::class)]
class DocumentEntityTest extends TestCase
{
    public function testOrderReferenceGettersReturnEmptyStringForMissingOrderReferenceUntilMajorCompatibilityBreak(): void
    {
        $document = new DocumentEntity();

        // @deprecated tag:v6.8.0 - expect null for both getters once the return types are widened.
        static::assertSame('', $document->getOrderId());
        static::assertSame('', $document->getOrderVersionId());
    }

    public function testAssignKeepsEmptyStringFallbackForNullOrderReferenceUntilMajorCompatibilityBreak(): void
    {
        $document = new DocumentEntity();

        $document->assign([
            'orderId' => null,
            'orderVersionId' => null,
        ]);

        // @deprecated tag:v6.8.0 - expect null for both getters once the return types are widened.
        static::assertSame('', $document->getOrderId());
        static::assertSame('', $document->getOrderVersionId());
    }
}
