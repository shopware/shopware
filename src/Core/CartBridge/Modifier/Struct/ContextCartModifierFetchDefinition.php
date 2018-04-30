<?php declare(strict_types=1);

namespace Shopware\CartBridge\Modifier\Struct;

use Shopware\Framework\Struct\Struct;

class ContextCartModifierFetchDefinition extends Struct
{
    /**
     * @var string[]
     */
    protected $contextRuleIds;

    /**
     * @param string[] $contextRuleIds
     */
    public function __construct(array $contextRuleIds)
    {
        $this->contextRuleIds = $contextRuleIds;
    }

    /**
     * @return string[]
     */
    public function getContextRuleIds(): array
    {
        return $this->contextRuleIds;
    }
}
