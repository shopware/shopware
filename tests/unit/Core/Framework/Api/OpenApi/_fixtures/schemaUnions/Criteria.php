<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
final class Criteria extends AbstractDto
{
    /**
     * @var list<EqualsFilter|RangeFilter>
     */
    #[Assert\Valid]
    public array $filter;

    #[Assert\Valid]
    public EqualsFilter|RangeFilter|null $query;

    /**
     * @var list<AverageAggregation|NestedCountAggregation>
     */
    #[Assert\Valid]
    public array $aggregations;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
