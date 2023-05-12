<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use OpenSearch\Client;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\Test\TestDefaults;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent;
use Shopware\Elasticsearch\Framework\Indexing\IndexCreator;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @group skip-paratest
 *
 * @package system-settings
 */
class ElasticsearchIndexerTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;
    use BasicTestDataBehaviour;

    protected function setUp(): void
    {
        $this->clearElasticsearch();
    }

    protected function tearDown(): void
    {
        $this->clearElasticsearch();
    }

    public function testFirstIndexDoesNotCreateTask(): void
    {
        $c = $this->getContainer()->get(Connection::class);
        static::assertEmpty($c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));

        $indexer = $this->getContainer()->get(ElasticsearchIndexer::class);
        $indexer->iterate(null);

        static::assertEmpty($c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));
    }

    public function testSecondIndexingCreatesTask(): void
    {
        $c = $this->getContainer()->get(Connection::class);
        $before = $c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        static::assertEmpty($before);

        $indexer = $this->getContainer()->get(ElasticsearchIndexer::class);
        $indexer->iterate(null);
        $indexer->iterate(null);

        $after = $c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        static::assertNotEmpty($after);
    }

    public function testItSkipsIndexGenerationForUnusedLanguages(): void
    {
        $container = $this->getContainer();
        $connection = $container->get(Connection::class);
        $languageRepository = $container->get(\sprintf('%s.repository', LanguageDefinition::ENTITY_NAME));
        $localeId = $this->getValidLocaleId();
        $languageId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        // Create a language without SalesChannelDomains
        $languageRepository->create(
            [
                [
                    'id' => $languageId,
                    'localeId' => $localeId,
                    'translationCodeId' => $localeId,
                    'name' => 'ElasticSearchIndexerTestLanguage',
                ],
            ],
            $context
        );

        $indexer = $this->getContainer()->get(ElasticsearchIndexer::class);
        // First index will not create indexes
        $indexer->iterate(null);
        $indexer->iterate(null);

        $languageRepository->delete([
            [
                'id' => $languageId,
            ],
        ], $context);

        // At least one index should exist for the system default language
        $allAssociative = $connection->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        static::assertNotEmpty($allAssociative);

        // Check that no alias contains the languageId
        foreach ($allAssociative as $index) {
            static::assertIsArray($index);
            static::assertArrayHasKey('alias', $index);
            static::assertStringNotContainsString($languageId, $index['alias']);
        }
    }

    /**
     * @throws Exception
     */
    public function testUpdateSkipsGeneratingIndexIfExists(): void
    {
        $container = self::getContainer();

        /** @var ElasticsearchIndexer $indexer */
        $indexer = $this->getContainer()->get(ElasticsearchIndexer::class);
        /** @var DefinitionInstanceRegistry $definitionRegistry */
        $definitionRegistry = $container->get(DefinitionInstanceRegistry::class);
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        /** @var EntityRepository $channelRepository */
        $channelRepository = $container->get('sales_channel.repository');
        /** @var EntityRepository $productRepository */
        $productRepository = $container->get('product.repository');
        /** @var Client $elasticsearchClient */
        $elasticsearchClient = $container->get(Client::class);

        $productDefinition = $definitionRegistry->getByEntityName(ProductDefinition::ENTITY_NAME);
        $context = Context::createDefaultContext();

        $connection->beginTransaction();

        // Make sure there are only sales channels without the default language
        $criteria = new Criteria();
        $existingSalesChannelsIds = $channelRepository->searchIds($criteria, $context)->getIds();
        $exisingSalesChannelsIdsToRemove = array_map(fn(string $id) => ['id' => $id], $existingSalesChannelsIds);
        $channelRepository->delete(array_values($exisingSalesChannelsIdsToRemove), $context);

        $languageId = $this->getNonDefaultLanguageId();
        $shippingMethodId = $this->getValidShippingMethodId();
        $paymentMethodId = $this->getValidPaymentMethodId();
        $countryId = $this->getValidCountryId(null);
        $salesChannel = [
            'id' => Uuid::randomHex(),
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'languageId' => $languageId,
            'currencyId' => Defaults::CURRENCY,
            'active' => true,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $shippingMethodId,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $countryId,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => $languageId]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'countries' => [['id' => $countryId]],
            'name' => 'first sales-channel',
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'url' => 'https://es-test.domain',
                    'languageId' => $languageId,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                ]
            ]
        ];
        $channelRepository->create([$salesChannel], $context);

        $productId = Uuid::randomHex();
        $product = [
            'id' => $productId,
            'productNumber' => 'Test',
            'stock' => 10,
            'name' => 'Test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
        ];
        $productRepository->create([$product], $context);

        $indexer->updateIds($productDefinition, [$productId]);
        // The index name is based on timestamp, so we have to wait at least 1 second to check if another one is created
        sleep(1);
        $indexer->updateIds($productDefinition, [$productId]);

        $connection->rollBack();

        $indicesStats = $elasticsearchClient->indices()->stats()['indices'] ?? [];

        self::assertCount(1, $indicesStats);
    }

    public function testIterateDispatchesElasticsearchIndexerLanguageCriteriaEvent(): void
    {
        $container = $this->getContainer();
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherMock
            ->expects(static::atLeast(1))
            ->method('dispatch')
            ->willReturnCallback(
                static function (ElasticsearchIndexerLanguageCriteriaEvent $event): void {
                    static::assertCount(1, $event->getCriteria()->getFilters());
                    static::assertInstanceOf(NandFilter::class, $event->getCriteria()->getFilters()[0]);
                }
            );

        $indexer = new ElasticsearchIndexer(
            $container->get(Connection::class),
            $container->get(ElasticsearchHelper::class),
            $container->get(ElasticsearchRegistry::class),
            $container->get(IndexCreator::class),
            $container->get(IteratorFactory::class),
            $container->get(Client::class),
            new NullLogger(),
            $container->get(\sprintf('%s.repository', CurrencyDefinition::ENTITY_NAME)),
            $container->get(\sprintf('%s.repository', LanguageDefinition::ENTITY_NAME)),
            $eventDispatcherMock,
            $container->getParameter('elasticsearch.indexing_batch_size'),
            $this->createMock(MessageBusInterface::class)
        );
        $indexer->iterate(null);
    }

    protected function getDiContainer(): ContainerInterface
    {
        return $this->getContainer();
    }

    protected function runWorker(): void
    {
    }

    private function getValidLocaleId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NandFilter([new EqualsFilter('id', $this->getLocaleIdOfSystemLanguage())]));
        $criteria->setLimit(1);

        $localeId = $this->getContainer()->get(\sprintf('%s.repository', LocaleDefinition::ENTITY_NAME))->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($localeId);

        return $localeId;
    }
}
