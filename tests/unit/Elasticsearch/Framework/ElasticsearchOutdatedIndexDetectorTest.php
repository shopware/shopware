<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;

/**
 * @internal
 */
#[CoversClass(ElasticsearchOutdatedIndexDetector::class)]
class ElasticsearchOutdatedIndexDetectorTest extends TestCase
{
    public function testUsesChunks(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects(static::exactly(2))
            ->method('get')
            ->willReturnCallback(fn () => [
                Uuid::randomHex() => [
                    'aliases' => [
                        'test',
                    ],
                    'settings' => [
                        'index' => [
                            'provided_name' => Uuid::randomHex(),
                        ],
                    ],
                ],
                Uuid::randomHex() => [
                    'aliases' => [],
                    'settings' => [
                        'index' => [
                            'provided_name' => Uuid::randomHex(),
                        ],
                    ],
                ],
            ]);

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $definition = $this->createMock(ElasticsearchProductDefinition::class);

        $registry = $this->createMock(ElasticsearchRegistry::class);
        $registry->method('getDefinitions')->willReturn([$definition, $definition]);

        $makeLanguage = fn () => (new LanguageEntity())->assign(['id' => Uuid::randomHex()]);

        $collection = new EntitySearchResult('test', 1, new LanguageCollection([$makeLanguage(), $makeLanguage(), $makeLanguage()]), null, new Criteria(), Context::createDefaultContext());

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->method('search')
            ->willReturn($collection);

        $esHelper = $this->createMock(ElasticsearchHelper::class);

        $detector = new ElasticsearchOutdatedIndexDetector($client, $registry, $esHelper);
        $arr = $detector->get();
        static::assertNotNull($arr);
        static::assertCount(1, $arr);
        static::assertCount(2, $detector->getAllUsedIndices());
    }

    public function testDoesNothingWithoutIndices(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects(static::exactly(0))
            ->method('get')
            ->willReturnCallback(fn () => []);

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $registry = $this->createMock(ElasticsearchRegistry::class);

        $esHelper = $this->createMock(ElasticsearchHelper::class);

        $detector = new ElasticsearchOutdatedIndexDetector($client, $registry, $esHelper);
        static::assertEmpty($detector->get());
    }
}
