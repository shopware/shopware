<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Rule;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Currency\CurrencyDefinition;

/**
 * @package business-ops
 */
class CurrencyRule extends Rule
{
    private const NAME = 'currency';

    /**
     * @var array<string>|null
     */
    protected $currencyIds;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @internal
     *
     * @param array<string>|null $currencyIds
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $currencyIds = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->currencyIds = $currencyIds;
    }

    public function match(RuleScope $scope): bool
    {
        return RuleComparison::uuids([$scope->getContext()->getCurrencyId()], $this->currencyIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'currencyIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('currencyIds', CurrencyDefinition::ENTITY_NAME, true);
    }
}
