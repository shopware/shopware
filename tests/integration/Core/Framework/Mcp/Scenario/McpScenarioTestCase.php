<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Result\McpToolResultParser;
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
use Shopware\Core\Framework\Util\Json;
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

        $criteriaBuilder = $container->get(RequestCriteriaBuilder::class);
        \assert($criteriaBuilder instanceof RequestCriteriaBuilder);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $encoder = $container->get(JsonEntityEncoder::class);
        \assert($encoder instanceof JsonEntityEncoder);

        $criteriaValidator = $container->get(AclCriteriaValidator::class);
        \assert($criteriaValidator instanceof AclCriteriaValidator);

        $this->entitySearchTool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder, $criteriaValidator);
        $this->entityAggregateTool = new EntityAggregateTool($registry, $criteriaBuilder, $contextProvider, $criteriaValidator);
        $this->entitySchemaTool = new EntitySchemaTool($registry);
        $this->entityReadTool = new EntityReadTool($registry, $criteriaBuilder, $contextProvider, $encoder, $criteriaValidator);

        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->entityUpsertTool = new EntityUpsertTool($registry, $contextProvider, $connection);

        $systemConfigService = $container->get(SystemConfigService::class);
        \assert($systemConfigService instanceof SystemConfigService);
        $this->systemConfigReadTool = new SystemConfigReadTool($systemConfigService, $contextProvider);
        $this->systemConfigWriteTool = new SystemConfigWriteTool($systemConfigService, $contextProvider);

        $stateMachineRegistry = $container->get(StateMachineRegistry::class);
        \assert($stateMachineRegistry instanceof StateMachineRegistry);
        $this->orderStateTool = new OrderStateTool($registry, $contextProvider, $stateMachineRegistry, $container->get(Connection::class));
    }

    protected function decodeToolOutput(McpToolResult|string $output): McpToolResult
    {
        $result = $this->toResult($output);
        static::assertFalse($result->isError(), 'Tool call failed: ' . $result->error?->message);

        return $result;
    }

    protected function decodeToolError(McpToolResult|string $output): McpToolError
    {
        $result = $this->toResult($output);
        static::assertTrue($result->isError());

        return $result->error;
    }

    private function toResult(McpToolResult|string $output): McpToolResult
    {
        if ($output instanceof McpToolResult) {
            return $output;
        }

        // Tools that still return the legacy string; the parser keeps JSON objects as objects.
        $result = (new McpToolResultParser())->parse($output);
        static::assertNotNull($result);

        return new McpToolResult(
            data: json_decode(Json::encode($result->data), true, 512, \JSON_THROW_ON_ERROR),
            error: $result->error,
            meta: json_decode(Json::encode((object) $result->meta), true, 512, \JSON_THROW_ON_ERROR),
        );
    }
}
