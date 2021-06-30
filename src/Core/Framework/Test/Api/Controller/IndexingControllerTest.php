<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Framework\Api\Controller\IndexingController;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

class IndexingControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testIterateIndexerApiShouldReturnFinishTrueWithInvalidIndexer(): void
    {
        $this->getBrowser()->request(
            'POST',
            '/api/_action/indexing/test.indexer',
            ['offset' => 0]
        );
        $response = $this->getBrowser()->getResponse();
        $response = json_decode($response->getContent(), true);

        static::assertTrue($response['finish']);
    }

    /**
     * @dataProvider provideOffsets
     */
    public function testIterateIndexerApiShouldReturnCorrectOffset(int $offset): void
    {
        $productIndexer = $this->createMock(ProductIndexer::class);
        if ($offset === 100) {
            $productIndexer->method('iterate')->willReturn(null);
        } else {
            $productIndexer->method('iterate')->willReturn(new ProductIndexingMessage(
                [
                    Uuid::randomHex(),
                ],
                ['offset' => $offset + 50]
            ));
        }
        $registry = $this->getMockBuilder(EntityIndexerRegistry::class)->disableOriginalConstructor()->getMock();
        $registry->method('getIndexer')->willReturn($productIndexer);
        $indexer = new IndexingController($registry, $this->getContainer()->get('messenger.bus.shopware'));

        $response = $indexer->iterate('product.indexer', new Request([], ['offset' => $offset]));
        $response = json_decode($response->getContent(), true);

        if ($offset === 100) {
            static::assertTrue($response['finish']);
        } else {
            static::assertFalse($response['finish']);
            static::assertEquals(['offset' => $offset + 50], $response['offset']);
        }
    }

    public function provideOffsets(): array
    {
        return [
            'offset 0' => [0],
            'offset 50' => [50],
            'offset 100' => [100],
        ];
    }
}
