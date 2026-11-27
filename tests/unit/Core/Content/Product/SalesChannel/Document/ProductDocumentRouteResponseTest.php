<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentRouteResponse::class)]
class ProductDocumentRouteResponseTest extends TestCase
{
    public function testReturnsTheResultAndItsDocuments(): void
    {
        $document = new ProductDocumentEntity();
        $document->setId(Uuid::randomHex());
        $documents = new ProductDocumentCollection([$document]);

        $result = new EntitySearchResult('product_document', 1, $documents, null, new Criteria(), Context::createDefaultContext());
        $response = new ProductDocumentRouteResponse($result);

        static::assertSame($result, $response->getResult());
        static::assertSame($documents, $response->getDocuments());
    }
}
