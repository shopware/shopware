<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Rule;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\FlowRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('services-settings')]
class OrderDocumentTypeSentRule extends FlowRule
{
    public const RULE_NAME = 'orderDocumentTypeSent';

    /**
     * @internal
     *
     * @param list<string> $documentIds
     */
    public function __construct(
        public string $operator = Rule::OPERATOR_EQ,
        public ?array $documentIds = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['documentIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof FlowRuleScope) {
            return false;
        }

        if (!$documents = $scope->getOrder()->getDocuments()) {
            return false;
        }

        $sentTypeIds = [];
        foreach ($documents->getElements() as $document) {
            if ($document->getSent()) {
                $sentTypeIds[] = $document->getDocumentTypeId();
            }
        }

        return RuleComparison::uuids(array_values(array_unique($sentTypeIds)), $this->documentIds, $this->operator);
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('documentIds', DocumentTypeDefinition::ENTITY_NAME, true);
    }
}
