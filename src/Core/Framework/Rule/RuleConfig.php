<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('business-ops')]
final class RuleConfig extends Struct
{
    public const OPERATOR_SET_DEFAULT = [Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ, Rule::OPERATOR_GTE, Rule::OPERATOR_LTE];

    public const OPERATOR_SET_STRING = [Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ];

    public const OPERATOR_SET_NUMBER = [
        Rule::OPERATOR_EQ,
        Rule::OPERATOR_GT,
        Rule::OPERATOR_GTE,
        Rule::OPERATOR_LT,
        Rule::OPERATOR_LTE,
        Rule::OPERATOR_NEQ,
    ];

    public const UNIT_DIMENSION = 'dimension';

    public const UNIT_WEIGHT = 'weight';

    public const UNIT_VOLUME = 'volume';

    public const UNIT_LENGTH = 'length';

    public const UNIT_TIME = 'time';

    public const UNIT_AGE = 'age';

    /**
     * @var array<string>|null
     */
    protected ?array $operators = null;

    protected bool $isMatchAny = false;

    /**
     * @var array<array<array<string>|string>|string>
     */
    protected array $fields = [];

    /**
     * @param array<string> $operators
     */
    public function operatorSet(array $operators, bool $addEmptyOperator = false, bool $isMatchAny = false): self
    {
        if ($addEmptyOperator) {
            $operators[] = Rule::OPERATOR_EMPTY;
        }

        $this->operators = $operators;
        $this->isMatchAny = $isMatchAny;

        return $this;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function entitySelectField(string $name, string $entity, bool $multi = false, array $config = []): self
    {
        $type = $multi ? 'multi-entity-id-select' : 'single-entity-id-select';

        return $this->field($name, $type, array_merge([
            'entity' => $entity,
        ], $config));
    }

    /**
     * @param array<string|int> $options
     * @param array<string, mixed> $config
     */
    public function selectField(string $name, array $options, bool $multi = false, array $config = []): self
    {
        $type = $multi ? 'multi-select' : 'single-select';

        return $this->field($name, $type, array_merge([
            'options' => $options,
        ], $config));
    }

    /**
     * @param array<string, mixed> $config
     */
    public function stringField(string $name, array $config = []): self
    {
        return $this->field($name, 'string', $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function numberField(string $name, array $config = []): self
    {
        return $this->field($name, 'float', $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function intField(string $name, array $config = []): self
    {
        return $this->field($name, 'int', $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function dateTimeField(string $name, array $config = []): self
    {
        return $this->field($name, 'datetime', $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function booleanField(string $name, array $config = []): self
    {
        return $this->field($name, 'bool', $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function taggedField(string $name, array $config = []): self
    {
        return $this->field($name, 'tagged', $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function field(string $name, string $type, array $config = []): self
    {
        $this->fields[] = $this->getFieldTemplate($name, $type, $config);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'operatorSet' => $this->operators ? [
                'operators' => $this->operators,
                'isMatchAny' => $this->isMatchAny,
            ] : null,
            'fields' => $this->fields,
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function getFieldTemplate(string $name, string $type, array $config): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'config' => $config,
        ];
    }
}
