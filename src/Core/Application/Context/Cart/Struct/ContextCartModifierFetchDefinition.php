<?php declare(strict_types=1);

namespace Shopware\Core\Application\Context\Cart\Struct;

use Shopware\Core\Framework\Struct\Struct;

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
