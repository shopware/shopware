<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class LanguageRule extends Rule
{
    /**
     * @var string[]|null
     */
    protected ?array $languageIds;

    protected string $operator;

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
        $languageId = $scope->getContext()->getLanguageId();

        if ($this->languageIds === null) {
            throw new UnsupportedValueException(\gettype($this->languageIds), self::class);
        }

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($languageId, $this->languageIds, true);

            case self::OPERATOR_NEQ:
                return !\in_array($languageId, $this->languageIds, true);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'operator' => [
                new NotBlank(),
                new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ]),
            ],
            'languageIds' => [
                new NotBlank(),
                new ArrayOfUuid(),
            ],
        ];
    }

    public function getName(): string
    {
        return 'language';
    }
}
