<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;

class JoinGroup extends Filter
{
    /**
     * @var SingleFieldFilter[]
     */
    protected $queries;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $suffix;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(array $queries, string $path, string $suffix, string $operator)
    {
        $this->queries = $queries;
        $this->path = $path;
        $this->suffix = $suffix;
        $this->operator = $operator;
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

    public function getPath(): string
    {
        return $this->path;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function setOperator(string $operator): void
    {
        $this->operator = $operator;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }
}
