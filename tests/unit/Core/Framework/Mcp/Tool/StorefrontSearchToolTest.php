<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\StorefrontSearchTool;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StorefrontSearchTool::class)]
class StorefrontSearchToolTest extends TestCase
{
    #[TestDox('returns correct JSON structure with meta data')]
    public function testReturnsCorrectJsonStructure(): void
    {
        $tool = $this->createTool(encodedResult: [['id' => 'prod-1']], total: 1);
        $output = ($tool)('sales-channel-123');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame(1, $data['_meta']['total']);
        static::assertArrayHasKey('data', $data);
        static::assertSame('sales-channel-123', $data['_meta']['salesChannelId']);
        static::assertSame('currency-123', $data['_meta']['currencyId']);
        static::assertNull($data['_meta']['customerId']);
    }

    #[TestDox('passes customerId to sales channel context')]
    public function testPassesCustomerIdToContext(): void
    {
        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService->expects($this->once())
            ->method('get')
            ->with(static::callback(function (SalesChannelContextServiceParameters $params): bool {
                return $params->getSalesChannelId() === 'sc-1' && $params->getCustomerId() === 'cust-1';
            }))
            ->willReturn($this->createSalesChannelContext());

        $tool = $this->createTool(contextService: $contextService);
        $output = ($tool)('sc-1', '{}', 'cust-1');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('cust-1', $data['_meta']['customerId']);
    }

    #[TestDox('passes term parameter into criteria payload')]
    public function testTermParameterIsPassedToCriteria(): void
    {
        $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturnCallback(function (array $payload) {
            static::assertSame('blue shirt', $payload['term']);

            $criteria = new Criteria();
            $criteria->setIncludes([]);

            return $criteria;
        });

        $tool = $this->createTool(criteriaBuilder: $criteriaBuilder);
        $output = ($tool)('sc-1', '{}', null, '{}', 'blue shirt');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
    }

    #[TestDox('resolves single property filter from database')]
    public function testResolvesSinglePropertyFilter(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['option_id' => 'opt-red', 'group_id' => 'grp-color', 'group_name' => 'Color', 'option_name' => 'Red'],
        ]);

        $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturnCallback(function (array $payload) {
            static::assertArrayHasKey('filter', $payload);
            static::assertSame('multi', $payload['filter'][0]['type']);
            static::assertSame('or', $payload['filter'][0]['operator']);

            $criteria = new Criteria();
            $criteria->setIncludes([]);

            return $criteria;
        });

        $tool = $this->createTool(connection: $connection, criteriaBuilder: $criteriaBuilder);
        $output = ($tool)('sc-1', '{}', null, json_encode(['Color' => 'Red'], \JSON_THROW_ON_ERROR));

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
    }

    #[TestDox('resolves multiple property filters with AND/OR structure')]
    public function testResolvesMultiplePropertyFilters(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['option_id' => 'opt-red', 'group_id' => 'grp-color', 'group_name' => 'Color', 'option_name' => 'Red'],
            ['option_id' => 'opt-xl', 'group_id' => 'grp-size', 'group_name' => 'Size', 'option_name' => 'XL'],
        ]);

        $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $criteriaBuilder->method('fromArray')->willReturnCallback(function (array $payload) {
            static::assertArrayHasKey('filter', $payload);
            $filter = $payload['filter'][0];
            static::assertSame('multi', $filter['type']);
            static::assertSame('and', $filter['operator']);
            static::assertCount(2, $filter['queries']);

            $criteria = new Criteria();
            $criteria->setIncludes([]);

            return $criteria;
        });

        $tool = $this->createTool(connection: $connection, criteriaBuilder: $criteriaBuilder);
        $properties = json_encode(['Color' => 'Red', 'Size' => 'XL'], \JSON_THROW_ON_ERROR);
        $output = ($tool)('sc-1', '{}', null, $properties);

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
    }

    #[TestDox('returns error when property group/option not found')]
    public function testPropertyNotFoundReturnsError(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $tool = $this->createTool(connection: $connection);
        $output = ($tool)('sc-1', '{}', null, json_encode(['Color' => 'Neon'], \JSON_THROW_ON_ERROR));

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Could not resolve properties', $data['error']);
        static::assertStringContainsString('Color', $data['error']);
        static::assertStringContainsString('Neon', $data['error']);
    }

    #[TestDox('returns error when ACL privilege is missing')]
    public function testDeniesAccessWithoutPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = $this->createTool(contextProvider: $contextProvider);
        $output = ($tool)('sc-1');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Missing privilege', $data['error']);
    }

    private function createSalesChannelContext(): SalesChannelContext&Stub
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getCurrencyId')->willReturn('currency-123');

        return $salesChannelContext;
    }

    /**
     * @param list<array<string, mixed>> $encodedResult
     */
    private function createTool(
        ?SalesChannelContextServiceInterface $contextService = null,
        ?Connection $connection = null,
        ?RequestCriteriaBuilder $criteriaBuilder = null,
        ?McpContextProvider $contextProvider = null,
        array $encodedResult = [],
        int $total = 0,
    ): StorefrontSearchTool {
        $context = Context::createDefaultContext();

        $salesChannelContext = $this->createSalesChannelContext();

        if ($contextService === null) {
            $contextService = static::createStub(SalesChannelContextServiceInterface::class);
            $contextService->method('get')->willReturn($salesChannelContext);
        }

        $result = new EntitySearchResult(
            'product',
            $total,
            new ProductCollection(),
            null,
            new Criteria(),
            $context,
        );

        $productRepository = static::createStub(SalesChannelRepository::class);
        $productRepository->method('search')->willReturn($result);

        $definition = static::createStub(EntityDefinition::class);
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn($definition);

        if ($criteriaBuilder === null) {
            $criteria = new Criteria();
            $criteria->setLimit(25);
            $criteria->setIncludes([]);
            $criteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
            $criteriaBuilder->method('fromArray')->willReturn($criteria);
        }

        $encoder = static::createStub(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn($encodedResult);

        if ($contextProvider === null) {
            $contextProvider = static::createStub(McpContextProvider::class);
            $contextProvider->method('getContext')->willReturn($context);
        }

        $connection ??= static::createStub(Connection::class);

        return new StorefrontSearchTool($contextService, $productRepository, $registry, $criteriaBuilder, $encoder, $contextProvider, $connection);
    }
}
