<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use OpenSearch\Client;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\ElasticsearchHelper
 */
class ElasticsearchHelperTest extends TestCase
{
    public function testLogAndThrowException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('critical');
        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            true,
            $this->createMock(Client::class),
            $this->createMock(ElasticsearchRegistry::class),
            $this->createMock(CriteriaParser::class),
            $logger,
            $this->createMock(AbstractKeyValueStorage::class)
        );

        static::expectException(\RuntimeException::class);

        static::assertFalse($helper->logAndThrowException(new \RuntimeException('test')));
    }

    public function testLogAndThrowExceptionOnlyLogs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('critical');
        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            false,
            $this->createMock(Client::class),
            $this->createMock(ElasticsearchRegistry::class),
            $this->createMock(CriteriaParser::class),
            $logger,
            $this->createMock(AbstractKeyValueStorage::class)
        );

        $helper->logAndThrowException(new \RuntimeException('test'));
    }

    public function testGetIndexName(): void
    {
        $storage = $this->createMock(AbstractKeyValueStorage::class);

        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            true,
            $this->createMock(Client::class),
            $this->createMock(ElasticsearchRegistry::class),
            $this->createMock(CriteriaParser::class),
            $this->createMock(LoggerInterface::class),
            $storage
        );

        if (Feature::isActive('ES_MULTILINGUAL_INDEX')) {
            static::assertSame('prefix_product', $helper->getIndexName(new ProductDefinition()));
        } else {
            static::assertSame('prefix_product_foo', $helper->getIndexName(new ProductDefinition(), 'foo'));
        }
    }

    public function testAllowSearch(): void
    {
        $registry = $this->createMock(ElasticsearchRegistry::class);
        $registry->method('has')->willReturnMap([
            ['product', true],
            ['category', false],
        ]);

        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            true,
            $this->createMock(Client::class),
            $registry,
            $this->createMock(CriteriaParser::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(AbstractKeyValueStorage::class)
        );

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        static::assertTrue(
            $helper->allowSearch(new ProductDefinition(), Context::createDefaultContext(), $criteria)
        );

        static::assertFalse(
            $helper->allowSearch(new CategoryDefinition(), Context::createDefaultContext(), $criteria)
        );

        $helper->setEnabled(false);

        static::assertFalse(
            $helper->allowSearch(new ProductDefinition(), Context::createDefaultContext(), $criteria)
        );
    }

    /**
     * @dataProvider enableMultilingualIndexCases
     */
    public function testEnableMultilingualIndex(?int $flag, bool $expected): void
    {
        Feature::skipTestIfInActive('ES_MULTILINGUAL_INDEX', $this);

        $registry = $this->createMock(ElasticsearchRegistry::class);
        $registry->method('has')->willReturnMap([
            ['product', true],
            ['category', false],
        ]);

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects(static::once())->method('get')->willReturn($flag);

        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            true,
            $this->createMock(Client::class),
            $registry,
            $this->createMock(CriteriaParser::class),
            $this->createMock(LoggerInterface::class),
            $storage
        );

        static::assertEquals($expected, $helper->enabledMultilingualIndex());
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function enableMultilingualIndexCases(): iterable
    {
        yield 'with flag not set' => [
            'flag' => null,
            'expected' => false,
        ];

        yield 'with flag disabled' => [
            'flag' => 0,
            'expected' => false,
        ];

        yield 'with flag enabled' => [
            'flag' => 1,
            'expected' => true,
        ];
    }
}
