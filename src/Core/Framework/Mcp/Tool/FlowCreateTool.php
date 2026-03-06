<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Content\Flow\FlowCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-flow-create', description: 'Create a Flow Builder automation. Supports a single event trigger with one action, optionally gated by a rule condition. Use shopware://business-events resource to discover events, shopware://flow-actions resource to discover actions, and shopware-entity-search on the "rule" entity to find existing rules. Returns the created flow ID. Defaults to dryRun=true.')]
#[Package('framework')]
class FlowCreateTool
{
    use McpToolResponse;

    /**
     * @internal
     *
     * @param EntityRepository<FlowCollection> $flowRepository
     */
    public function __construct(
        private readonly EntityRepository $flowRepository,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(
        string $name,
        string $eventName,
        string $actionName,
        string $actionConfig = '{}',
        string $ruleId = '',
        string $description = '',
        int $priority = 1,
        bool $active = false,
        bool $dryRun = true,
    ): string {
        $context = $this->contextProvider->getContext();

        if (!$context->isAllowed('flow:create')) {
            return $this->error('Missing privilege: flow:create');
        }

        try {
            $config = json_decode($actionConfig, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->error('Invalid actionConfig JSON: ' . $e->getMessage());
        }

        if (!\is_array($config)) {
            return $this->error('actionConfig must be a JSON object.');
        }

        if ($ruleId !== '') {
            $conditionSequenceId = Uuid::randomHex();

            $sequences = [
                [
                    'id' => $conditionSequenceId,
                    'ruleId' => $ruleId,
                    'actionName' => null,
                    'config' => [],
                    'position' => 1,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => $conditionSequenceId,
                    'ruleId' => null,
                    'actionName' => $actionName,
                    'config' => $config,
                    'position' => 1,
                    'trueCase' => true,
                ],
            ];
        } else {
            $sequences = [
                [
                    'id' => Uuid::randomHex(),
                    'actionName' => $actionName,
                    'config' => $config,
                    'position' => 1,
                    'trueCase' => false,
                ],
            ];
        }

        $flowPayload = [
            'name' => $name,
            'eventName' => $eventName,
            'description' => $description,
            'priority' => $priority,
            'active' => $active,
            'sequences' => $sequences,
        ];

        if ($dryRun) {
            return $this->success($flowPayload, ['dryRun' => true, 'sequenceCount' => \count($sequences)]);
        }

        $flowId = Uuid::randomHex();
        $flowPayload['id'] = $flowId;

        try {
            $this->flowRepository->create([$flowPayload], $context);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }

        return $this->success([
            'flowId' => $flowId,
            'name' => $name,
            'eventName' => $eventName,
            'active' => $active,
        ], ['dryRun' => false]);
    }
}
