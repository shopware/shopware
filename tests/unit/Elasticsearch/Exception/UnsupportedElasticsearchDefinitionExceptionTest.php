<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Exception\UnsupportedElasticsearchDefinitionException;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Exception\UnsupportedElasticsearchDefinitionException
 */
class UnsupportedElasticsearchDefinitionExceptionTest extends TestCase
{
    public function testException(): void
    {
        $exception = new UnsupportedElasticsearchDefinitionException('product');
        static::assertStringContainsString('Entity product is not supported for elastic search', $exception->getMessage());
        static::assertSame('ELASTICSEARCH_UNSUPPORTED_DEFINITION', $exception->getErrorCode());
    }
}
