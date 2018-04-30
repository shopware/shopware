<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search\Query;

class NestedQuery extends Query
{
    /**
     * @var Query[]
     */
    protected $queries;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(array $queries = [], string $operator = 'AND')
    {
        $this->queries = $queries;
        $this->operator = $operator;
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
