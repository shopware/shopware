<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Indexing\FlowBuilder;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class Sequence
{
    /**
     * @param list<Sequence> $children
     * @param array<string, mixed> $config
     */
    public function __construct(
        public string $flowId,
        public ?string $sequenceId = null,
        public ?string $appFlowActionId = null,
        public ?string $parentId = null,
        public ?string $ruleId = null,
        public ?string $actionName = null,
        public array $config = [],
        public int $position = 1,
        public int $displayGroup = 1,
        public bool $trueCase = false,
        public array $children = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function createFromDb(array $data): self
    {
        return new self(
            $data['flow_id'],
            $data['sequence_id'],
            $data['app_flow_action_id'],
            $data['parent_id'],
            $data['rule_id'],
            $data['action_name'],
            json_decode($data['config'] ?? '[]', true, flags: \JSON_THROW_ON_ERROR),
            (int) $data['position'],
            (int) $data['display_group'],
            (bool) $data['true_case'],
        );
    }
}
