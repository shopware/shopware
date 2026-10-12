<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use OpenSearch\Common\Exceptions\BadRequest400Exception;
use OpenSearch\Exception\BadRequestHttpException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Elasticsearch\Product\ElasticsearchProductException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchProductException::class)]
class ElasticsearchProductExceptionTest extends TestCase
{
    public function testExpectedArray(): void
    {
        $previous = new BadRequestHttpException('test');
        $e = ElasticsearchProductException::cannotChangeCustomFieldType($previous);

        static::assertSame('One or more custom fields already exist in the index with different types. Please reset the index and rebuild it.', $e->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('ELASTICSEARCH_PRODUCT__CANNOT_CHANGE_CUSTOM_FIELD_TYPE', $e->getErrorCode());
        static::assertSame($previous, $e->getPrevious());
    }

    public function testCannotChangeFieldType(): void
    {
        $previous = new BadRequestHttpException('mapper_parsing_exception');
        $exception = ElasticsearchProductException::cannotChangeFieldType($previous);

        static::assertSame(ElasticsearchProductException::ES_PRODUCT_CANNOT_CHANGE_FIELD_TYPE, $exception->getErrorCode());
        static::assertSame('One or more fields already exist in the index with different types. Please reset the index and rebuild it.', $exception->getMessage());
        static::assertSame($previous, $exception->getPrevious());
    }

    /**
     * @deprecated tag:v6.8.0 - reason: BadRequest400Exception support is removed with the next major - to be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCannotChangeFieldTypeWithLegacyException(): void
    {
        $previous = new BadRequest400Exception('mapper_parsing_exception');
        $exception = ElasticsearchProductException::cannotChangeFieldType($previous);

        static::assertSame(ElasticsearchProductException::ES_PRODUCT_CANNOT_CHANGE_FIELD_TYPE, $exception->getErrorCode());
        static::assertSame($previous, $exception->getPrevious());
    }
}
