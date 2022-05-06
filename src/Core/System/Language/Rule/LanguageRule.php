<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

class LanguageRule extends Rule
{
    /**
     * @var string[]|null
     */
    protected ?array $languageIds;

    protected string $operator;

    /**
     * @internal
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
}
