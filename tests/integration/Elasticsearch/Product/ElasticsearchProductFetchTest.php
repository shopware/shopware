<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Shopware\Tests\Unit\Core\System\Language\Stubs\StaticSalesChannelLanguageLoader;

/**
 * @internal
 */
#[Package('framework')]
class ElasticsearchProductFetchTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var list<string>
     */
    private array $channelIds = [];

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        for ($i = 0; $i < 3; ++$i) {
            $this->channelIds[] = $this->createSalesChannel([
                'id' => Uuid::randomHex(),
                'domains' => [[
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://es-fetch-' . Uuid::randomHex() . '.example',
                ]],
            ])['id'];
        }

        $builder = (new ProductBuilder($this->ids, 'product'))
            ->price(10)
            ->visibility($this->channelIds[0], ProductVisibilityDefinition::VISIBILITY_ALL)
            ->visibility($this->channelIds[1], ProductVisibilityDefinition::VISIBILITY_SEARCH)
            ->visibility($this->channelIds[2], ProductVisibilityDefinition::VISIBILITY_LINK)
            ->tag('tag-a')
            ->tag('tag-b')
            ->tag('tag-c');

        static::getContainer()->get('product.repository')->create([$builder->build()], Context::createDefaultContext());
    }

    public function testFetchedDocumentContainsEveryVisibilityAndTagOnce(): void
    {
        $definition = static::getContainer()->get(ElasticsearchProductDefinition::class);

        $documents = $definition->fetch(Uuid::fromHexToBytesList([$this->ids->get('product')]), Context::createDefaultContext());

        static::assertArrayHasKey($this->ids->get('product'), $documents);
        $document = $documents[$this->ids->get('product')];

        $expectedVisibilities = [
            $this->channelIds[0] => ProductVisibilityDefinition::VISIBILITY_ALL,
            $this->channelIds[1] => ProductVisibilityDefinition::VISIBILITY_SEARCH,
            $this->channelIds[2] => ProductVisibilityDefinition::VISIBILITY_LINK,
        ];
        ksort($expectedVisibilities);

        $nestedVisibilities = array_column($document['visibilities'], 'visibility', 'salesChannelId');
        ksort($nestedVisibilities);
        static::assertSame($expectedVisibilities, $nestedVisibilities);
        static::assertCount(3, $document['visibilities']);
        foreach ($document['visibilities'] as $visibility) {
            static::assertSame(1, $visibility['_count']);
        }

        foreach ($expectedVisibilities as $salesChannelId => $visibility) {
            static::assertSame($visibility, $document['visibility_' . $salesChannelId]);
        }

        $tags = array_column($document['tags'], 'name', 'id');
        ksort($tags);
        $expectedTags = [
            $this->ids->get('tag-a') => 'tag-a',
            $this->ids->get('tag-b') => 'tag-b',
            $this->ids->get('tag-c') => 'tag-c',
        ];
        ksort($expectedTags);
        static::assertSame($expectedTags, $tags);
    }

    public function testProductRowsBeforeGroupingDoNotMultiplyVisibilitiesWithTags(): void
    {
        $sql = $this->captureProductQuery();
        static::assertStringContainsString('GROUP BY p.id', $sql);

        $countSql = (string) preg_replace('/^SELECT.*?\nFROM product p\b/s', 'SELECT COUNT(*) FROM product p', $sql);
        $countSql = str_replace('GROUP BY p.id', '', $countSql);

        $rows = (int) static::getContainer()->get(Connection::class)->fetchOne(
            $countSql,
            [
                'ids' => Uuid::fromHexToBytesList([$this->ids->get('product')]),
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        // one row per tag, not one row per tag and sales channel (3 x 3)
        static::assertSame(3, $rows);
    }

    private function captureProductQuery(): string
    {
        $queries = [];
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociativeIndexed')->willReturnCallback(static function (string $sql) use (&$queries): array {
            $queries[] = $sql;

            return [];
        });

        $definition = new ElasticsearchProductDefinition(
            new ProductDefinition(),
            $connection,
            static::createStub(ProductSearchQueryBuilder::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            static::createStub(ElasticsearchFieldMapper::class),
            new StaticSalesChannelLanguageLoader([Defaults::LANGUAGE_SYSTEM => []]),
            false,
            'test',
            static::createStub(LanguageLoaderInterface::class)
        );

        $definition->fetch(Uuid::fromHexToBytesList([$this->ids->get('product')]), Context::createDefaultContext());

        static::assertNotEmpty($queries);

        return $queries[0];
    }
}
