<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * The MCP entity tools must apply the same association ACL checks to
 * client-supplied criteria as the Admin API (AclCriteriaValidator): a caller
 * with only the top-level read privilege must not be able to load associated
 * entities that require their own read privilege.
 *
 * @internal
 */
#[Package('framework')]
class EntityToolAclCriteriaIntegrationTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testSearchDeniesAssociationWithoutItsReadPrivilege(): void
    {
        $tool = $this->createSearchTool(['order:read']);

        $output = ($tool)('order', json_encode([
            'associations' => ['orderCustomer' => []],
        ], \JSON_THROW_ON_ERROR));

        $this->assertMissingPrivilege($output, 'order_customer:read');
    }

    public function testSearchDeniesAssociationFieldFilterWithoutItsReadPrivilege(): void
    {
        $tool = $this->createSearchTool(['order:read']);

        $output = ($tool)('order', json_encode([
            'filter' => [
                ['type' => 'equals', 'field' => 'orderCustomer.email', 'value' => 'a@b.c'],
            ],
        ], \JSON_THROW_ON_ERROR));

        $this->assertMissingPrivilege($output, 'order_customer:read');
    }

    public function testSearchAllowsAssociationWithItsReadPrivilege(): void
    {
        $tool = $this->createSearchTool(['order:read', 'order_customer:read']);

        $output = ($tool)('order', json_encode([
            'associations' => ['orderCustomer' => []],
        ], \JSON_THROW_ON_ERROR));

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success'], 'Search with granted association privilege should succeed, got: ' . $output);
    }

    public function testSearchWithoutAssociationsStillWorksWithTopLevelPrivilege(): void
    {
        $tool = $this->createSearchTool(['order:read']);

        $output = ($tool)('order');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success'], 'Plain top-level search should succeed, got: ' . $output);
    }

    public function testReadDeniesAssociationWithoutItsReadPrivilege(): void
    {
        $tool = $this->createReadTool(['order:read']);

        $output = ($tool)('order', Uuid::randomHex(), json_encode([
            'associations' => ['orderCustomer' => []],
        ], \JSON_THROW_ON_ERROR));

        $this->assertMissingPrivilege($output, 'order_customer:read');
    }

    public function testAggregateDeniesAssociationFieldWithoutItsReadPrivilege(): void
    {
        $tool = $this->createAggregateTool(['order:read']);

        $output = ($tool)(
            'order',
            json_encode([
                ['type' => 'terms', 'name' => 'emails', 'field' => 'orderCustomer.email'],
            ], \JSON_THROW_ON_ERROR),
        );

        $this->assertMissingPrivilege($output, 'order_customer:read');
    }

    /**
     * @param list<string> $permissions
     */
    private function createSearchTool(array $permissions): EntitySearchTool
    {
        return new EntitySearchTool(
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            $this->criteriaBuilder(),
            $this->contextProvider($permissions),
            $this->encoder(),
            $this->criteriaValidator(),
        );
    }

    /**
     * @param list<string> $permissions
     */
    private function createReadTool(array $permissions): EntityReadTool
    {
        return new EntityReadTool(
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            $this->criteriaBuilder(),
            $this->contextProvider($permissions),
            $this->encoder(),
            $this->criteriaValidator(),
        );
    }

    /**
     * @param list<string> $permissions
     */
    private function createAggregateTool(array $permissions): EntityAggregateTool
    {
        return new EntityAggregateTool(
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            $this->criteriaBuilder(),
            $this->contextProvider($permissions),
            $this->criteriaValidator(),
        );
    }

    /**
     * @param list<string> $permissions
     */
    private function contextProvider(array $permissions): McpContextProvider
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($permissions);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $provider = $this->createMock(McpContextProvider::class);
        $provider->method('getContext')->willReturn($context);

        return $provider;
    }

    private function criteriaBuilder(): RequestCriteriaBuilder
    {
        $builder = static::getContainer()->get(RequestCriteriaBuilder::class);
        \assert($builder instanceof RequestCriteriaBuilder);

        return $builder;
    }

    private function encoder(): JsonEntityEncoder
    {
        $encoder = static::getContainer()->get(JsonEntityEncoder::class);
        \assert($encoder instanceof JsonEntityEncoder);

        return $encoder;
    }

    private function criteriaValidator(): AclCriteriaValidator
    {
        $validator = static::getContainer()->get(AclCriteriaValidator::class);
        \assert($validator instanceof AclCriteriaValidator);

        return $validator;
    }

    private function assertMissingPrivilege(string $output, string $privilege): void
    {
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success'], 'Expected ACL denial, got: ' . $output);
        static::assertStringContainsString('Missing privilege:', $data['error']);
        static::assertStringContainsString($privilege, $data['error']);
    }
}
