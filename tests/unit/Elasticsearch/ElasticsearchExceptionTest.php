<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\ElasticsearchException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ElasticsearchException::class)]
class ElasticsearchExceptionTest extends TestCase
{
    public function testDefinitionNotFound(): void
    {
        $exception = ElasticsearchException::definitionNotFound('product');

        static::assertSame('ELASTICSEARCH__DEFINITION_NOT_FOUND', $exception->getErrorCode());
        static::assertSame('Definition product not found', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testUnsupportedDefinition(): void
    {
        $exception = ElasticsearchException::unsupportedElasticsearchDefinition('product');

        static::assertSame('ELASTICSEARCH__UNSUPPORTED_DEFINITION', $exception->getErrorCode());
        static::assertSame('Definition product is not supported for elasticsearch', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testIndexingError(): void
    {
        $exception = ElasticsearchException::indexingError([
            ['reason' => 'foo'],
            ['reason' => 'bar'],
        ]);

        static::assertSame('ELASTICSEARCH__INDEXING_ERROR', $exception->getErrorCode());
        static::assertSame("Following errors occurred while indexing: \nfoo\nbar", $exception->getMessage());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testNestedAggregationMissingInFilterAggregation(): void
    {
        $exception = ElasticsearchException::nestedAggregationMissingInFilterAggregation('foo');

        static::assertSame('ELASTICSEARCH__NESTED_FILTER_AGGREGATION_MISSING', $exception->getErrorCode());
        static::assertSame('Filter aggregation foo contains no nested aggregation.', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testUnsupportedAggregation(): void
    {
        $exception = ElasticsearchException::unsupportedAggregation('foo');

        static::assertSame('ELASTICSEARCH__UNSUPPORTED_AGGREGATION', $exception->getErrorCode());
        static::assertSame('Provided aggregation of class foo is not supported', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testUnsupportedFilter(): void
    {
        $exception = ElasticsearchException::unsupportedFilter('foo');

        static::assertSame('ELASTICSEARCH__UNSUPPORTED_FILTER', $exception->getErrorCode());
        static::assertSame('Provided filter of class foo is not supported', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testNestedAggregationParseError(): void
    {
        $exception = ElasticsearchException::nestedAggregationParseError('foo');

        static::assertSame('ELASTICSEARCH__NESTED_AGGREGATION_PARSE_ERROR', $exception->getErrorCode());
        static::assertSame('Nested filter aggregation foo can not be parsed.', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testParentFilterError(): void
    {
        $exception = ElasticsearchException::parentFilterError('foo');

        static::assertSame('ELASTICSEARCH__PARENT_FILTER_ERROR', $exception->getErrorCode());
        static::assertSame('Expected nested+filter+reverse pattern for parsed filter foo to set next parent correctly.', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testServerNotAvailableError(): void
    {
        $exception = ElasticsearchException::serverNotAvailable();

        static::assertSame('ELASTICSEARCH__SERVER_NOT_AVAILABLE', $exception->getErrorCode());
        static::assertSame('Elasticsearch server is not available', $exception->getMessage());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testEmptyQueryError(): void
    {
        $exception = ElasticsearchException::emptyQuery();

        static::assertSame('ELASTICSEARCH__EMPTY_QUERY', $exception->getErrorCode());
        static::assertSame('Empty query provided', $exception->getMessage());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }
}
