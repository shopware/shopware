<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Indexer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\AdminSearchController;
use Shopware\Elasticsearch\Admin\AdminSearcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Admin\AdminSearchController
 */
class AdminSearchControllerTest extends TestCase
{
    private AdminSearchController $controller;

    public function setUp(): void
    {
        $searcher = $this->getMockBuilder(AdminSearcher::class)->disableOriginalConstructor()->getMock();

        $promotion = new PromotionEntity();
        $promotion->setUniqueIdentifier(Uuid::randomHex());
        $searcher->method('search')->willReturn([
            'promotion' => [
                'total' => 1,
                'data' => new EntityCollection([$promotion]),
                'indexer' => 'promotion-listing',
                'index' => 'sw-admin-promotion-listing',
            ],
        ]);

        $this->controller = new AdminSearchController(
            $searcher,
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(JsonEntityEncoder::class)
        );
    }

    public function testElasticSearch(): void
    {
        $request = new Request();
        $request->attributes->set('term', 'test');
        $response = $this->controller->elastic($request, Context::createDefaultContext());

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        $content = \json_decode($content, true);
        $data = $content['data'];

        static::assertNotEmpty($data['promotion']);

        static::assertEquals(1, $data['promotion']['total']);
        static::assertNotEmpty($data['promotion']['data']);
        static::assertEquals('promotion-listing', $data['promotion']['indexer']);
        static::assertEquals('sw-admin-promotion-listing', $data['promotion']['index']);
    }
}
