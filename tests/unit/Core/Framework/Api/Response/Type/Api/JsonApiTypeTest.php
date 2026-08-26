<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Response\Type\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Response\Type\Api\JsonApiType;
use Shopware\Core\Framework\Api\Serializer\JsonApiEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(JsonApiType::class)]
class JsonApiTypeTest extends TestCase
{
    public function testCreateListingResponseEncodesEntities(): void
    {
        $definition = new ProductDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $product = (new ProductEntity())->assign([
            'id' => 'product-id',
            '_uniqueIdentifier' => 'product-id',
        ]);

        $searchResult = new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            1,
            new ProductCollection([$product]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $type = new JsonApiType(new JsonApiEncoder(), static::createStub(StructEncoder::class));

        $response = $type->createListingResponse(
            new Criteria(),
            $searchResult,
            $definition,
            Request::create('/api/product'),
            Context::createDefaultContext()
        );

        static::assertSame(200, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $decoded['meta']['total']);
        static::assertCount(1, $decoded['data']);
        static::assertSame('product-id', $decoded['data'][0]['id']);
        static::assertSame(ProductDefinition::ENTITY_NAME, $decoded['data'][0]['type']);
    }

    public function testCreateListingResponseAddsLastLinkBeforeNextPagesSentinel(): void
    {
        $criteria = (new Criteria())
            ->setLimit(2)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        $response = $this->createListingResponse($criteria, 12);

        $content = $response->getContent();
        static::assertIsString($content);
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('http://localhost/api/product?limit=2&page=6', $decoded['links']['last']);
    }

    public function testCreateListingResponseOmitsLastLinkAtNextPagesSentinel(): void
    {
        $criteria = (new Criteria())
            ->setLimit(2)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        $response = $this->createListingResponse($criteria, 13);

        $content = $response->getContent();
        static::assertIsString($content);
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('last', $decoded['links']);
        static::assertSame('http://localhost/api/product?limit=2&page=2', $decoded['links']['next']);
    }

    private function createListingResponse(Criteria $criteria, int $total): Response
    {
        $definition = new ProductDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $searchResult = new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            $total,
            new ProductCollection(),
            null,
            $criteria,
            Context::createDefaultContext()
        );

        return (new JsonApiType(new JsonApiEncoder(), static::createStub(StructEncoder::class)))->createListingResponse(
            $criteria,
            $searchResult,
            $definition,
            Request::create('/api/product'),
            Context::createDefaultContext()
        );
    }
}
