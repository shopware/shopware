<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 *
 * @group skip-paratest
 *
 * @package system-settings
 */
class ElasticsearchIndexerTest extends TestCase
{
    use BasicTestDataBehaviour;
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

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
        static::assertNotNull($indexer);
        $indexer->iterate(null);

        static::assertEmpty($c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));
    }

    public function testSecondIndexingCreatesTask(): void
    {
        $c = $this->getContainer()->get(Connection::class);
        $before = $c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        static::assertEmpty($before);

        $indexer = $this->getContainer()->get(ElasticsearchIndexer::class);
        static::assertNotNull($indexer);

        $indexer->iterate(null);
        $indexer->iterate(null);

        $after = $c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        static::assertNotEmpty($after);
    }

    public function testItSkipsIndexGenerationForUnusedLanguages(): void
    {
        Feature::skipTestIfActive('ES_MULTILINGUAL_INDEX', $this);

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

        static::assertNotNull($indexer);
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
