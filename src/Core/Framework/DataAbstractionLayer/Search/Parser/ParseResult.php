<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ParseResult
{
    /**
     * @var list<string>
     */
    protected array $wheres = [];

    /**
     * @var array<string, mixed>
     */
    protected array $parameters = [];

    /**
     * @var array<string, ParameterType|ArrayParameterType>
     */
    protected array $types = [];

    public function addWhere(string $queryString): void
    {
        $this->wheres[] = $queryString;
    }

    public function addParameter(string $key, mixed $value, ParameterType|ArrayParameterType $type = ParameterType::STRING): void
    {
        $this->parameters[$key] = $value;
        $this->types[$key] = $type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<string, ParameterType|ArrayParameterType>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @return list<string>
     */
    public function getWheres(): array
    {
        return array_values(array_filter($this->wheres));
    }

    public function getType(string $key): ParameterType|ArrayParameterType
    {
        return $this->types[$key] ?? ParameterType::STRING;
    }

    public function merge(self $toMerge): ParseResult
    {
        $merged = new self();
        foreach ($this->parameters as $key => $parameter) {
            $merged->addParameter($key, $parameter, $this->types[$key]);
        }
        foreach ($this->wheres as $where) {
            $merged->addWhere($where);
        }

        foreach ($toMerge->getParameters() as $key => $parameter) {
            $merged->addParameter($key, $parameter, $toMerge->getType($key));
        }
        foreach ($toMerge->getWheres() as $where) {
            $merged->addWhere($where);
        }

        return $merged;
    }

    public function resetWheres(): void
    {
        $this->wheres = [];
    }
}
