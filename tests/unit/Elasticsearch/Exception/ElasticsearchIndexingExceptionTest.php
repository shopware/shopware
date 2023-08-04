<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Elasticsearch\Exception\ElasticsearchIndexingException;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Exception\ElasticsearchIndexingException
 */
class ElasticsearchIndexingExceptionTest extends TestCase
{
    public function testException(): void
    {
        Feature::skipTestIfActive('ES_MULTILINGUAL_INDEX', $this);

        $exception = new ElasticsearchIndexingException([
            'index' => 'shopware',
            'type' => 'product',
            'reason' => 'illegal_argument_exception',
            'id' => '1',
            'error' => [
                'type' => 'illegal_argument_exception',
                'reason' => 'illegal argument',
            ],
        ]);

        static::assertStringContainsString('Following errors occurred while indexing', $exception->getMessage());
        static::assertStringContainsString('illegal argument', $exception->getMessage());
        static::assertSame('ELASTICSEARCH_INDEXING', $exception->getErrorCode());
    }
}
