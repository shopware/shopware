<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Elasticsearch\Exception\NoIndexedDocumentsException;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Exception\NoIndexedDocumentsException
 */
class NoIndexedDocumentsExceptionTest extends TestCase
{
    public function testException(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $exception = new NoIndexedDocumentsException('product');

        static::assertStringContainsString('No indexed documents found for entity product', $exception->getMessage());
        static::assertSame('ELASTICSEARCH_NO_INDEXED_DOCUMENTS', $exception->getErrorCode());
    }
}
