<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

class MultiFilter extends Filter
{
    public const CONNECTION_AND = 'AND';
    public const CONNECTION_OR = 'OR';

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
        $this->operator = $operator;
        $this->queries = $queries;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getOperator(): string
    {
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
