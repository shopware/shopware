<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Query\QueryBuilder;

class FieldAccessor
{
    /**
     * @var string
     */
    private $sql;

    /**
     * @var array
     */
    private $parameters = [];

    public function __construct(string $sql, array $parameters = [])
    {
        $this->sql = $sql;
        foreach ($parameters as $key => $value) {
            $this->addParameter($key, $value);
        }
    }

    public function getSQL(): string
    {
        return $this->sql;
    }

    public function addParameter($key, $value)
    {
        $this->parameters[$key] = $value;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function addParametersToQuery(QueryBuilder $builder): void
    {
        foreach ($this->parameters as $key => $value) {
            $builder->setParameter($key, $value);
        }
    }
}
