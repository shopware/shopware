<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

class MultiFilter extends Filter
{
    public const CONNECTION_AND = 'AND';
    public const CONNECTION_OR = 'OR';
    public const CONNECTION_XOR = 'XOR';

    public const VALID_OPERATORS = [
        self::CONNECTION_AND,
        self::CONNECTION_OR,
        self::CONNECTION_XOR,
    ];

    /**
     * @var Filter[]
     */
    protected $queries;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(string $operator, array $queries = [])
    {
        $this->operator = mb_strtoupper(trim($operator));
        $this->queries = $queries;

        if (!\in_array($this->operator, self::VALID_OPERATORS, true)) {
            throw new \InvalidArgumentException('Operator ' . $this->operator . ' not allowed');
        }
    }

    public function addQuery(Filter $query): self
    {
        $this->queries[] = $query;

        return $this;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getOperator(): string
    {
        if (!\in_array($this->operator, self::VALID_OPERATORS, true)) {
            throw new \InvalidArgumentException('Operator ' . $this->operator . ' not allowed');
        }

        return $this->operator;
    }

    public function getFields(): array
    {
        $fields = [];
        foreach ($this->queries as $query) {
            foreach ($query->getFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
