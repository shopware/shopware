<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopware\Core\Framework\Mcp\Tool\OrderStateTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
abstract class McpScenarioTestCase extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    protected EntitySearchTool $entitySearchTool;

    protected EntityAggregateTool $entityAggregateTool;

    protected EntitySchemaTool $entitySchemaTool;

    protected EntityReadTool $entityReadTool;

    protected EntityUpsertTool $entityUpsertTool;

    protected SystemConfigReadTool $systemConfigReadTool;

    protected SystemConfigWriteTool $systemConfigWriteTool;

    protected OrderStateTool $orderStateTool;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $registry = $container->get(DefinitionInstanceRegistry::class);

        /** @var RequestCriteriaBuilder $criteriaBuilder */
        $criteriaBuilder = $container->get(RequestCriteriaBuilder::class);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        /** @var JsonEntityEncoder $encoder */
        $encoder = $container->get(JsonEntityEncoder::class);

        $this->entitySearchTool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder);
        $this->entityAggregateTool = new EntityAggregateTool($registry, $criteriaBuilder, $contextProvider);
        $this->entitySchemaTool = new EntitySchemaTool($registry);
        $this->entityReadTool = new EntityReadTool($registry, $criteriaBuilder, $contextProvider, $encoder);

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $this->entityUpsertTool = new EntityUpsertTool($registry, $contextProvider, $connection);

        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $container->get(SystemConfigService::class);
        $this->systemConfigReadTool = new SystemConfigReadTool($systemConfigService, $contextProvider);
        $this->systemConfigWriteTool = new SystemConfigWriteTool($systemConfigService, $contextProvider);

        /** @var StateMachineRegistry $stateMachineRegistry */
        $stateMachineRegistry = $container->get(StateMachineRegistry::class);
        $this->orderStateTool = new OrderStateTool($registry, $contextProvider, $stateMachineRegistry, $container->get(Connection::class));
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeToolOutput(string $json): array
    {
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertTrue($data['success'], 'Tool call failed: ' . ($data['error'] ?? 'unknown'));

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeToolError(string $json): array
    {
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertFalse($data['success']);

        return $data;
    }
}
