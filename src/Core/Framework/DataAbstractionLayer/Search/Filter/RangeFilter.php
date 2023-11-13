<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class RangeFilter extends SingleFieldFilter
{
    final public const LTE = 'lte';

    final public const LT = 'lt';

    final public const GTE = 'gte';

    final public const GT = 'gt';

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
     *
     * @param array<string, float|int|string> $parameters
     */
    public function __construct(
        protected string $field,
        protected array $parameters = []
    ) {
    }

    public function hasParameter(string $key): bool
    {
        return \array_key_exists($key, $this->parameters);
    }

    public function getParameter(string $key): float|int|string|null
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

    /**
     * @return array<string, float|int|string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getFields(): array
    {
        return [$this->field];
    }
}
