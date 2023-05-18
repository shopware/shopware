<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingException
 */
#[Package('core')]
class ElasticsearchIndexingExceptionTest extends TestCase
{
    public function testIndexingError(): void
    {
        $res = ElasticsearchIndexingException::indexingError([[
            'index' => 'index',
            'id' => 'id',
            'type' => 'error',
            'reason' => 'Foo Error',
        ]]);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $res->getStatusCode());
        static::assertEquals(ElasticsearchIndexingException::ES_INDEXING_ERROR, $res->getErrorCode());
        static::assertStringContainsString($res->getMessage(), 'Following errors occurred while indexing: ' . \PHP_EOL . 'Foo Error');
    }

    public function testDefinitionNotFound(): void
    {
        $res = ElasticsearchIndexingException::definitionNotFound('foo');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $res->getStatusCode());
        static::assertEquals(ElasticsearchIndexingException::ES_DEFINITION_NOT_FOUND, $res->getErrorCode());
        static::assertEquals('Elasticsearch definition of foo not found', $res->getMessage());
    }
}
