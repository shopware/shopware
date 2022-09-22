<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Elasticsearch\Admin\AdminSearchIndexingMessage;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\PromotionAdminSearchIndexer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 * @group skip-paratest
 */
class AdminSearchControllerTest extends TestCase
{
    use KernelTestBehaviour;
    use AdminApiTestBehaviour;

    private AdminSearchRegistry $registry;

    private PromotionAdminSearchIndexer $indexer;

    public function setUp(): void
    {
        $this->indexer = new PromotionAdminSearchIndexer(
            $this->getContainer()->get(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->getContainer()->get('promotion.repository')
        );

        $this->registry = new AdminSearchRegistry(
            ['promotion-listing' => $this->indexer],
            $this->createMock(MessageBusInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->getContainer()->get(Client::class),
            $this->createMock(SystemConfigService::class),
            false,
            [],
            []
        );
    }

    public function testElasticSearch(): void
    {
        $id = 'c1a28776116d4431a2208eb2960ec340';
        $this->createPromotion([
            'id' => $id,
            'name' => 'elasticsearch',
        ]);

        $indices = ['sw-admin-promotion-listing' => 'sw-admin-promotion-listing'];
        $this->registry->handle(
            new AdminSearchIndexingMessage(
                $this->indexer->getName(),
                $indices,
                [$id]
            )
        );

        $data = [
            'term' => 'elasticsearch',
            'entities' => ['promotion'],
        ];

        $this->getBrowser()->request('POST', '/api/_admin/es-search', [], [], [], json_encode($data) ?: null);

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $content = json_decode($content, true);

        static::assertNotEmpty($content['data']);
        static::assertNotEmpty($content['data']['promotion']);

        $data = $content['data']['promotion'];

        static::assertEquals(1, $data['total']);
        static::assertNotEmpty($data['data'][$id]);
        static::assertEquals($id, $data['data'][$id]['id']);
        static::assertEquals('elasticsearch', $data['data'][$id]['name']);
    }

    /**
     * @param array<string, mixed> $promotionOverride
     *
     * @return array<string, mixed>
     */
    private function createPromotion(array $promotionOverride = []): array
    {
        /** @var EntityRepositoryInterface $promotionRepository */
        $promotionRepository = $this->getContainer()->get('promotion.repository');

        $promotion = array_merge([
            'id' => $promotionOverride['id'] ?? Uuid::randomHex(),
            'name' => 'Test case promotion',
            'active' => true,
            'useIndividualCodes' => true,
        ], $promotionOverride);

        $promotionRepository->upsert([$promotion], Context::createDefaultContext());

        return $promotion;
    }
}
