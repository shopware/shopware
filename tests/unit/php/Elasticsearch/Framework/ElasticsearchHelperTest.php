<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use OpenSearch\Client;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Test\Annotation\DisabledFeatures;
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
            $logger
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
            $logger
        );

        $helper->logAndThrowException(new \RuntimeException('test'));
    }

    public function testGetIndexName(): void
    {
        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            true,
            $this->createMock(Client::class),
            $this->createMock(ElasticsearchRegistry::class),
            $this->createMock(CriteriaParser::class),
            $this->createMock(LoggerInterface::class)
        );

        static::assertSame('prefix_product_foo', $helper->getIndexName(new ProductDefinition(), 'foo'));
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
            $this->createMock(LoggerInterface::class)
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
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testAllowSearchDeprecated(): void
    {
        $registry = $this->createMock(ElasticsearchRegistry::class);
        $registry->method('has')->willReturn(true);

        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            true,
            $this->createMock(Client::class),
            $registry,
            $this->createMock(CriteriaParser::class),
            $this->createMock(LoggerInterface::class)
        );

        static::assertFalse($helper->allowSearch(new ProductDefinition(), Context::createDefaultContext()));
        $context = Context::createDefaultContext();
        $context->addState(Context::STATE_ELASTICSEARCH_AWARE);
        static::assertTrue($helper->allowSearch(new ProductDefinition(), $context));
    }
}
