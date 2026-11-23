<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;

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

    public function testEveryProductOfABatchKeepsItsOwnVisibilitiesAndTags(): void
    {
        $this->createProducts(
            (new ProductBuilder(ids: $this->ids, productNumber: 'second-product'))
                ->price(gross: 10)
                ->visibility(salesChannelId: $this->channelIds[1], visibility: ProductVisibilityDefinition::VISIBILITY_SEARCH)
                ->tag(key: 'tag-d'),
        );

        $documents = $this->fetch('product', 'second-product');

        static::assertCount(2, $documents);

        $second = $documents[$this->ids->get('second-product')];
        static::assertSame(
            [$this->channelIds[1] => ProductVisibilityDefinition::VISIBILITY_SEARCH],
            array_column($second['visibilities'], 'visibility', 'salesChannelId')
        );
        static::assertSame([$this->ids->get('tag-d') => 'tag-d'], array_column($second['tags'], 'name', 'id'));

        $first = $documents[$this->ids->get('product')];
        static::assertCount(3, $first['visibilities']);
        static::assertCount(3, $first['tags']);
        static::assertArrayNotHasKey($this->ids->get('tag-d'), array_column($first['tags'], 'name', 'id'));
    }

    public function testProductWithoutTagsIsIndexedWithoutTags(): void
    {
        $this->createProducts(
            (new ProductBuilder(ids: $this->ids, productNumber: 'untagged-product'))
                ->price(gross: 10)
                ->visibility(salesChannelId: $this->channelIds[0], visibility: ProductVisibilityDefinition::VISIBILITY_ALL),
        );

        $document = $this->fetch('untagged-product')[$this->ids->get('untagged-product')];

        static::assertSame([], $document['tags']);
        static::assertSame(ProductVisibilityDefinition::VISIBILITY_ALL, $document['visibility_' . $this->channelIds[0]]);
    }

    public function testProductWithoutVisibilitiesIsNotIndexed(): void
    {
        $this->createProducts(
            (new ProductBuilder(ids: $this->ids, productNumber: 'invisible-product'))
                ->price(gross: 10)
                ->tag(key: 'tag-a'),
        );

        $documents = $this->fetch('product', 'invisible-product');

        static::assertArrayHasKey($this->ids->get('product'), $documents);
        static::assertArrayNotHasKey($this->ids->get('invisible-product'), $documents);
    }

    private function createProducts(ProductBuilder ...$builders): void
    {
        static::getContainer()->get('product.repository')->create(
            array_map(static fn (ProductBuilder $builder) => $builder->build(), $builders),
            Context::createDefaultContext()
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetch(string ...$keys): array
    {
        return static::getContainer()->get(ElasticsearchProductDefinition::class)->fetch(
            Uuid::fromHexToBytesList(array_map(fn (string $key) => $this->ids->get($key), $keys)),
            Context::createDefaultContext()
        );
    }
}
