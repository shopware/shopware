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

    /**
     * @var array
     */
    private $parameterTypes = [];

    public function __construct(string $sql, array $parameters = [], array $types = [])
    {
        $this->sql = $sql;
        foreach ($parameters as $key => $value) {
            $this->addParameter($key, $value, $types[$key] ?? null);
        }
    }

    public function getSQL(): string
    {
        return $this->sql;
    }

    /**
     * @param string|int      $key
     * @param mixed           $value
     * @param string|int|null $type  one of the {@link \Doctrine\DBAL\ParameterType} constants
     */
    public function addParameter($key, $value, $type = null): void
    {
        $this->parameters[$key] = $value;
        $this->parameterTypes[$key] = $type;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameterTypes(): array
    {
        return $this->parameterTypes;
    }

    public function addParametersToQuery(QueryBuilder $builder): void
    {
        foreach ($this->parameters as $key => $value) {
            $builder->setParameter($key, $value, $this->parameterTypes[$key]);
        }
    }
}
