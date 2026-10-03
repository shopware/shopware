<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminIndexingCommand;
use Shopware\Elasticsearch\Test\AdminElasticsearchTestBehaviour;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
class AdminSearchControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AdminElasticsearchTestBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<PromotionCollection>
     */
    private EntityRepository $promotionRepository;

    /**
     * Built once for the whole class by the first run of setUp(). The first-test-indexes pattern was
     * replaced by guarded setUp because a data-provided test (testElasticSearch) can no longer also
     * receive the ids via #[Depends] - see NoDependsWithDataProviderRule.
     */
    private static IdsCollection $indexedIds;

    public static function tearDownAfterClass(): void
    {
        $container = static::getContainer();

        // the promotions are committed once for the whole class, so the tests running after it would see them
        $container->get(Connection::class)->executeStatement('DELETE FROM promotion');

        // rebuild the admin promotion index from the emptied table, otherwise it keeps reporting the deleted promotions
        $adminEsHelper = $container->get(AdminElasticsearchHelper::class);
        $adminEsHelper->setEnabled(true);

        try {
            $container->get(ElasticsearchAdminIndexingCommand::class)
                ->run(new ArrayInput(['--only' => 'promotion', '--no-queue' => true]), new NullOutput());
            $container->get(Client::class)->indices()->refresh(['index' => '*']);
        } finally {
            $adminEsHelper->setEnabled(false);
        }
    }

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        $this->promotionRepository = static::getContainer()->get('promotion.repository');

        if (!isset(self::$indexedIds)) {
            self::$indexedIds = $this->buildIndex();
        }
    }

    /**
     * @param array{term: string, entities: list<string>} $data
     * @param list<string> $expectedPromotions
     */
    #[DataProvider('providerSearchCases')]
    public function testElasticSearch(array $data, array $expectedPromotions): void
    {
        $ids = self::$indexedIds;

        $this->getBrowser()->request('POST', '/api/_admin/es-search', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data, \JSON_THROW_ON_ERROR) ?: null);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content, print_r($content, true));
        static::assertNotEmpty($content['data']);
        static::assertNotEmpty($content['data']['promotion']);

        $content = $content['data']['promotion'];

        static::assertSame(\count($expectedPromotions), $content['total']);

        foreach ($expectedPromotions as $expectedPromotion) {
            $id = $ids->get($expectedPromotion);
            static::assertNotEmpty($content['data'][$id]);
            static::assertSame($id, $content['data'][$id]['id']);
        }
    }

    /**
     * @return \Generator<string, array{array{term: string, entities: list<string>}, list<string>}>
     */
    public static function providerSearchCases(): \Generator
    {
        yield 'search with normal term' => [
            [
                'term' => 'laptop gold',
                'entities' => ['promotion'],
            ],
            ['promotion-1', 'promotion-2', 'promotion-3'],
        ];
        yield 'search with OR' => [
            [
                'term' => 'laptop OR gold',
                'entities' => ['promotion'],
            ],
            ['promotion-1', 'promotion-2', 'promotion-3'],
        ];
        yield 'search with OR syntax' => [
            [
                'term' => 'laptop | gold',
                'entities' => ['promotion'],
            ],
            ['promotion-1', 'promotion-2', 'promotion-3'],
        ];
        yield 'search with Umlauts' => [
            [
                'term' => 'Ausländer',
                'entities' => ['promotion'],
            ],
            ['promotion-5'],
        ];
        yield 'search by number #1 with concatenated index' => [
            [
                'term' => '12345',
                'entities' => ['promotion'],
            ],
            ['promotion-6'],
        ];
        yield 'search by number #2 with concatenated index' => [
            [
                'term' => '56789',
                'entities' => ['promotion'],
            ],
            ['promotion-6'],
        ];
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    private function buildIndex(): IdsCollection
    {
        $this->connection->executeStatement('DELETE FROM promotion');

        $this->clearElasticsearch();
        $this->indexElasticSearch(['--only' => ['promotion']]);

        $ids = new IdsCollection();
        $this->createData($ids);

        $this->refreshIndex();

        return $ids;
    }

    private function createData(IdsCollection $ids): void
    {
        $promotions = [
            [
                'id' => $ids->get('promotion-1'),
                'name' => 'gold laptop',
                'active' => true,
                'useIndividualCodes' => true,
            ],
            [
                'id' => $ids->get('promotion-2'),
                'name' => 'silver laptop',
                'active' => true,
                'useIndividualCodes' => true,
            ],
            [
                'id' => $ids->get('promotion-3'),
                'name' => 'gold pc',
                'active' => true,
                'useIndividualCodes' => true,
            ],
            [
                'id' => $ids->get('promotion-4'),
                'name' => 'silver pc',
                'active' => true,
                'useIndividualCodes' => true,
            ],
            [
                'id' => $ids->get('promotion-5'),
                'name' => 'Ausländer',
                'active' => true,
                'useIndividualCodes' => true,
            ],
            [
                'id' => $ids->get('promotion-6'),
                'name' => [
                    'de-DE' => '12345',
                    'en-GB' => '56789',
                ],
                'active' => true,
                'useIndividualCodes' => true,
            ],
        ];

        $this->promotionRepository->create($promotions, Context::createDefaultContext());
    }
}
