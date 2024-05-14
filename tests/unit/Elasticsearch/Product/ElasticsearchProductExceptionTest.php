<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use OpenSearch\Common\Exceptions\BadRequest400Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Product\ElasticsearchProductException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ElasticsearchProductException::class)]
class ElasticsearchProductExceptionTest extends TestCase
{
    public function testExpectedArray(): void
    {
        $previous = new BadRequest400Exception('test');
        $e = ElasticsearchProductException::cannotChangeCustomFieldType($previous);

        static::assertEquals('One or more custom fields already exist in the index with different types. Please reset the index and rebuild it.', $e->getMessage());
        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('ELASTICSEARCH_PRODUCT__CANNOT_CHANGE_CUSTOM_FIELD_TYPE', $e->getErrorCode());
        static::assertSame($previous, $e->getPrevious());
    }
}
