<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Query;

class MultiFilter extends Query
{
    public const CONNECTION_AND = 'AND';
    public const CONNECTION_OR = 'OR';

    /**
     * @var Query[]
     */
    protected $queries;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(string $operator = self::CONNECTION_AND, array $queries = [])
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
