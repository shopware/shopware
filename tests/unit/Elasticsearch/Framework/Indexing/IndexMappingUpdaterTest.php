<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchLanguageProvider;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingProvider;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\IndexMappingUpdater
 */
class IndexMappingUpdaterTest extends TestCase
{
    public function testUpdate(): void
    {
        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());

        $languageProvider = $this->createMock(ElasticsearchLanguageProvider::class);
        $languageProvider
            ->method('getLanguages')
            ->willReturn(new LanguageCollection([$language]));

        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');

        $registry = new ElasticsearchRegistry([
            $this->createMock(ElasticsearchProductDefinition::class),
        ]);

        $client = $this->createMock(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects(static::once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ]);

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = $this->createMock(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $languageProvider,
            $client,
            $indexMappingProvider
        );

        $updater->update(Context::createDefaultContext());
    }
}
