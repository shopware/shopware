<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Test\AdminElasticsearchTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Group('skip-paratest')]
class AdminSearchControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AdminElasticsearchTestBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    private EntityRepository $promotionRepo;

    protected function setUp(): void
    {
        if (!$this->getContainer()->getParameter('elasticsearch.administration.enabled')) {
            static::markTestSkipped('No OPENSEARCH configured');
        }

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->promotionRepo = $this->getContainer()->get('promotion.repository');
    }

    public function testIndexing(): IdsCollection
    {
        static::expectNotToPerformAssertions();

        $this->connection->executeStatement('DELETE FROM promotion');

        $this->clearElasticsearch();
        $this->indexElasticSearch(['--only' => ['promotion']]);

        $ids = new IdsCollection();
        $this->createData($ids);

        $this->refreshIndex();

        return $ids;
    }

    /**
     * @param array<string, string> $data
     * @param array<string> $expectedPromotions
     */
    #[Depends('testIndexing')]
    #[DataProvider('providerSearchCases')]
    public function testElasticSearch(array $data, array $expectedPromotions, IdsCollection $ids): void
    {
        $this->getBrowser()->request('POST', '/api/_admin/es-search', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR) ?: null);
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content, print_r($content, true));
        static::assertNotEmpty($content['data']);
        static::assertNotEmpty($content['data']['promotion']);

        $content = $content['data']['promotion'];

        static::assertEquals(\count($expectedPromotions), $content['total']);

        foreach ($expectedPromotions as $expectedPromotion) {
            $id = $ids->get($expectedPromotion);
            static::assertNotEmpty($content['data'][$id]);
            static::assertEquals($id, $content['data'][$id]['id']);
        }
    }

    /**
     * @return iterable<string, array{array<string, string|array<string>>, array<string>}>
     */
    public static function providerSearchCases(): iterable
    {
        yield 'search with normal term' => [
            [
                'term' => 'laptop gold',
                'entities' => ['promotion'],
            ],
            ['promotion-1', 'promotion-2', 'promotion-3'],
        ];
        yield 'search a phrase' => [
            [
                'term' => '"gold laptop"',
                'entities' => ['promotion'],
            ],
            ['promotion-1'],
        ];
        yield 'search with AND' => [
            [
                'term' => 'laptop AND gold',
                'entities' => ['promotion'],
            ],
            ['promotion-1'],
        ];
        yield 'search with OR' => [
            [
                'term' => 'laptop OR gold',
                'entities' => ['promotion'],
            ],
            ['promotion-1', 'promotion-2', 'promotion-3'],
        ];
        yield 'search with AND syntax' => [
            [
                'term' => '+laptop +gold',
                'entities' => ['promotion'],
            ],
            ['promotion-1'],
        ];
        yield 'search with OR syntax' => [
            [
                'term' => 'laptop | gold',
                'entities' => ['promotion'],
            ],
            ['promotion-1', 'promotion-2', 'promotion-3'],
        ];
        yield 'search with NEGATE syntax' => [
            [
                'term' => 'laptop +-gold',
                'entities' => ['promotion'],
            ],
            ['promotion-2'],
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
        return $this->getContainer();
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

        $this->promotionRepo->create($promotions, Context::createDefaultContext());
    }
}
