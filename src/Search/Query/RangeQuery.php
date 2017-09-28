<?php declare(strict_types=1);

namespace Shopware\Search\Query;

class RangeQuery extends Query
{
    const LTE = 'lte';

    const LT = 'lt';

    const GTE = 'gte';

    const GT = 'gt';

    /**
     * @var string
     */
    protected $field;

    /**
     * @var array
     */
    protected $parameters = [];

    /**
     * @example
     *
     * new RangeQuery('price', [
     *      '>=' => 5.99,
     *      '<=' => 21.99
     * ])
     *
     * new RangeQuery('price', [
     *      '>' => 5.99
     * ])
     *
     * @param string $field
     * @param array  $parameters
     */
    public function __construct(string $field, array $parameters = [])
    {
        $this->field = $field;
        $this->parameters = $parameters;
    }

    public function hasParameter(string $key)
    {
        return array_key_exists($key, $this->parameters);
    }

    public function getParameter(string $key)
    {
        return $this->parameters[$key];
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getFields(): array
    {
        return [$this->field];
    }
}
