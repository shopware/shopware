<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class ParseResult
{
    /**
     * @var list<string>
     */
    protected array $wheres = [];

    protected array $parameters = [];

    protected array $types = [];

    public function addWhere(string $queryString): void
    {
        $this->wheres[] = $queryString;
    }

    public function addParameter(string $key, $value, $type = null): void
    {
        $this->parameters[$key] = $value;
        $this->types[$key] = $type;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getTypes(): array
    {
        return array_filter($this->types);
    }

    /**
     * @return list<string>
     */
    public function getWheres(): array
    {
        return array_values(array_filter($this->wheres));
    }

    public function getType(string $key)
    {
        return $this->types[$key] ?: null;
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
