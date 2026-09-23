<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\Tool\OrderStateTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResponse::class)]
class AclEnforcementTest extends TestCase
{
    public function testEntitySearchToolDenied(): void
    {
        $tool = new EntitySearchTool(
            $this->createRegistryWithEntity(),
            static::createStub(RequestCriteriaBuilder::class),
            $this->createDeniedContextProvider(),
            static::createStub(JsonEntityEncoder::class),
            static::createStub(AclCriteriaValidator::class),
        );

        $this->assertAclDenied(($tool)('product'), 'product:read');
    }

    public function testEntityReadToolDenied(): void
    {
        $tool = new EntityReadTool(
            $this->createRegistryWithEntity(),
            static::createStub(RequestCriteriaBuilder::class),
            $this->createDeniedContextProvider(),
            static::createStub(JsonEntityEncoder::class),
            static::createStub(AclCriteriaValidator::class),
        );

        $this->assertAclDenied(($tool)('product', 'some-id'), 'product:read');
    }

    public function testEntityDeleteToolDenied(): void
    {
        $tool = new EntityDeleteTool(
            $this->createRegistryWithEntity(),
            $this->createDeniedContextProvider(),
            static::createStub(Connection::class),
        );

        $this->assertAclDenied(($tool)('product', 'some-id'), 'product:delete');
    }

    public function testEntityUpsertToolDenied(): void
    {
        $tool = new EntityUpsertTool(
            $this->createRegistryWithEntity(),
            $this->createDeniedContextProvider(),
            static::createStub(Connection::class),
        );

        $this->assertAclDenied(($tool)('product', '{"name":"test"}'), 'product:create');
    }

    public function testSystemConfigReadToolDenied(): void
    {
        $tool = new SystemConfigReadTool(
            static::createStub(SystemConfigService::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('core.listing'), 'system_config:read');
    }

    public function testSystemConfigWriteToolDenied(): void
    {
        $tool = new SystemConfigWriteTool(
            static::createStub(SystemConfigService::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('core.test', '"value"'), 'system_config:update');
    }

    public function testOrderStateToolDenied(): void
    {
        $tool = new OrderStateTool(
            static::createStub(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
            static::createStub(StateMachineRegistry::class),
            static::createStub(Connection::class),
        );

        $this->assertAclDenied(($tool)(orderNumber: '10001', orderAction: 'cancel'), 'order:read');
    }

    public function testEntityAggregateToolDenied(): void
    {
        $tool = new EntityAggregateTool(
            $this->createRegistryWithEntity(),
            static::createStub(RequestCriteriaBuilder::class),
            $this->createDeniedContextProvider(),
            static::createStub(AclCriteriaValidator::class),
        );

        $this->assertAclDenied(($tool)('product', '[]'), 'product:read');
    }

    public function testSystemConfigReadToolAllowed(): void
    {
        $configService = static::createStub(SystemConfigService::class);
        $configService->method('get')->willReturn('test-value');

        $tool = new SystemConfigReadTool(
            $configService,
            $this->createAllowedContextProvider('system_config:read'),
        );

        $this->assertAclAllowed(($tool)('core.listing.defaultSorting'));
    }

    public function testSystemConfigWriteToolAllowed(): void
    {
        $configService = static::createStub(SystemConfigService::class);
        $configService->method('get')->willReturn('old-value');

        $tool = new SystemConfigWriteTool(
            $configService,
            $this->createAllowedContextProvider('system_config:update'),
        );

        $this->assertAclAllowed(($tool)('core.test', '"new-value"'));
    }

    public function testEntityDeleteToolAllowed(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getRepository')->willReturn(static::createStub(EntityRepository::class));

        $tool = new EntityDeleteTool(
            $registry,
            $this->createAllowedContextProvider('product:delete'),
            static::createStub(Connection::class),
        );

        $this->assertAclAllowed(($tool)('product', 'some-id'));
    }

    private function createRegistryWithEntity(): DefinitionInstanceRegistry
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);

        return $registry;
    }

    private function createDeniedContextProvider(): McpContextProvider
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $provider = static::createStub(McpContextProvider::class);
        $provider->method('getContext')->willReturn($context);

        return $provider;
    }

    private function createAllowedContextProvider(string ...$permissions): McpContextProvider
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($permissions);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $provider = static::createStub(McpContextProvider::class);
        $provider->method('getContext')->willReturn($context);

        return $provider;
    }

    private function assertAclDenied(string $output, string $expectedPrivilege): void
    {
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('Missing privilege', $data['error']);
        static::assertStringContainsString($expectedPrivilege, $data['error']);
    }

    private function assertAclAllowed(string $output): void
    {
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success'], 'Tool should succeed when ACL permissions are granted, got: ' . $output);
    }
}
