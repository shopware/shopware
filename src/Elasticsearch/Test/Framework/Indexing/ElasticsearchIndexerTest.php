<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Framework\Indexing;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent;
use Shopware\Elasticsearch\Framework\Indexing\IndexCreator;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @group skip-paratest
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
        static::assertEmpty($c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));

        $indexer = $this->getContainer()->get(ElasticsearchIndexer::class);
        $indexer->iterate(null);
        $indexer->iterate(null);

        static::assertNotEmpty($c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));
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
            $container->getParameter('elasticsearch.indexing_batch_size')
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

    private function clearElasticsearch(): void
    {
        $c = KernelLifecycleManager::getKernel()->getContainer();

        $client = $c->get(Client::class);

        $client->indices()->delete(['index' => '_all']);
        $client->indices()->refresh(['index' => '_all']);

        $connection = $c->get(Connection::class);
        $connection->executeStatement('DELETE FROM elasticsearch_index_task');
    }
}
