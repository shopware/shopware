<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Language\LanguageDefinition;

/**
 * @package business-ops
 */
class LanguageRule extends Rule
{
    /**
     * @var array<string>|null
     */
    protected ?array $languageIds;

    protected string $operator;

    /**
     * @internal
     *
     * @param array<string>|null $languageIds
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?array $languageIds = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->languageIds = $languageIds;
    }

    /**
     * @throws UnsupportedOperatorException|UnsupportedValueException
     */
    public function match(RuleScope $scope): bool
    {
        if ($this->languageIds === null) {
            throw new UnsupportedValueException(\gettype($this->languageIds), self::class);
        }

        return RuleComparison::uuids([$scope->getContext()->getLanguageId()], $this->languageIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::uuidOperators(false),
            'languageIds' => RuleConstraints::uuids(),
        ];
    }

    public function getName(): string
    {
        return 'language';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('languageIds', LanguageDefinition::ENTITY_NAME, true);
    }
}
