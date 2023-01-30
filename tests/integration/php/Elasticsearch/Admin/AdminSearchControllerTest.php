<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\AdminSearchController;
use Shopware\Elasticsearch\Test\AdminElasticsearchTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @package system-settings
 *
 * @internal
 *
 * @group skip-paratest
 */
class AdminSearchControllerTest extends TestCase
{
    use KernelTestBehaviour;
    use AdminApiTestBehaviour;
    use AdminElasticsearchTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    private EntityRepository $promotionRepo;

    private AdminSearchController $controller;

    public function setUp(): void
    {
        $this->clearElasticsearch();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->promotionRepo = $this->getContainer()->get('promotion.repository');

        $this->controller = $this->getContainer()->get(AdminSearchController::class);
    }

    public function testElasticSearch(): void
    {
        if (!$this->getContainer()->getParameter('elasticsearch.administration.enabled')) {
            static::markTestSkipped('No OPENSEARCH configured');
        }

        $this->connection->executeStatement('DELETE FROM promotion');

        $id = 'c1a28776116d4431a2208eb2960ec340';
        $this->createPromotion([
            'id' => $id,
            'name' => 'elasticsearch',
        ]);

        $this->indexElasticSearch(['--only' => ['promotion']]);

        $request = new Request();
        $request->request->set('term', 'elasticsearch');
        $request->request->set('entities', ['promotion']);
        $response = $this->controller->elastic($request, Context::createDefaultContext());

        static::assertEquals(200, $response->getStatusCode());

        $content = $response->getContent();
        static::assertNotFalse($content);

        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotEmpty($content['data']);
        static::assertNotEmpty($content['data']['promotion']);

        $data = $content['data']['promotion'];

        static::assertEquals(1, $data['total']);
        static::assertNotEmpty($data['data'][$id]);
        static::assertEquals($id, $data['data'][$id]['id']);
        static::assertEquals('elasticsearch', $data['data'][$id]['name']);
    }

    protected function getDiContainer(): ContainerInterface
    {
        return $this->getContainer();
    }

    /**
     * @param array<string, mixed> $promotionOverride
     *
     * @return array<string, mixed>
     */
    private function createPromotion(array $promotionOverride = []): array
    {
        $promotion = array_merge([
            'id' => $promotionOverride['id'] ?? Uuid::randomHex(),
            'name' => 'Test case promotion',
            'active' => true,
            'useIndividualCodes' => true,
        ], $promotionOverride);

        $this->promotionRepo->upsert([$promotion], Context::createDefaultContext());

        return $promotion;
    }
}
