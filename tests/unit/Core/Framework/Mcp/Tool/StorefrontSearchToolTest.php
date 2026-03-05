<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\StorefrontSearchTool;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StorefrontSearchTool::class)]
class StorefrontSearchToolTest extends TestCase
{
    public function testReturnsCorrectJsonStructure(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getCurrencyId')->willReturn('currency-123');

        $entity = new ProductEntity();
        $entity->setId('prod-1');
        $entity->setUniqueIdentifier('prod-1');
        $result = new EntitySearchResult(
            'product',
            1,
            new ProductCollection([$entity]),
            null,
            new Criteria(),
            $context,
        );

        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService->method('get')->willReturn($salesChannelContext);

        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->method('search')->willReturn($result);

        $definition = $this->createMock(EntityDefinition::class);
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->with('product')->willReturn($definition);

        $criteria = new Criteria();
        $criteria->setLimit(25);
        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturn($criteria);

        $tool = new StorefrontSearchTool($contextService, $productRepository, $registry, $criteriaBuilder);
        $output = ($tool)('sales-channel-123');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $data['total']);
        static::assertArrayHasKey('data', $data);
        static::assertArrayHasKey('_meta', $data);
        static::assertSame('sales-channel-123', $data['_meta']['salesChannelId']);
        static::assertSame('currency-123', $data['_meta']['currencyId']);
    }
}
