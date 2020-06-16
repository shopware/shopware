<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

class RangeFilter extends Filter
{
    public const LTE = 'lte';

    public const LT = 'lt';

    public const GTE = 'gte';

    public const GT = 'gt';

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
     * new RangeFilter('price', [
     *      RangeFilter::GTE => 5.99,
     *      RangeFilter::LTE => 21.99
     * ])
     *
     * new RangeFilter('price', [
     *      RangeFilter::GT => 5.99
     * ])
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
        if (!$this->hasParameter($key)) {
            return null;
        }

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
