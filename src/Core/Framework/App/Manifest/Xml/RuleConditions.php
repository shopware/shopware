<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class RuleConditions extends XmlElement
{
    /**
     * @var RuleCondition[]
     */
    protected $ruleConditions = [];

    private function __construct(array $ruleConditions)
    {
        $this->ruleConditions = $ruleConditions;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseRuleConditions($element));
    }

    /**
     * @return RuleCondition[]
     */
    public function getRuleConditions(): array
    {
        return $this->ruleConditions;
    }

    /**
     * @return RuleCondition[]
     */
    private static function parseRuleConditions(\DOMElement $element): array
    {
        $ruleConditions = [];
        foreach ($element->getElementsByTagName('rule-condition') as $ruleCondition) {
            $ruleConditions[] = RuleCondition::fromXml($ruleCondition);
        }

        return $ruleConditions;
    }
}
