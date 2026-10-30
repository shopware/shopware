<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeWidening;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class NotFilter extends MultiFilter
{
    /**
     * @param array<Filter> $queries
     */
    #[ParameterTypeWidening(version: 'v6.8.0', parameterName: 'operator', newType: 'string|' . Filter::class, description: 'Accepts the single filter to negate, like NotFilter::negate().')]
    public function __construct(
        string $operator,
        array $queries = []
    ) {
        parent::__construct($operator, $queries);
    }

    public static function negate(Filter $filter): self
    {
        return new self(self::CONNECTION_AND, [$filter]);
    }
}
