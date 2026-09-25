<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * Enriches a criteria with a product stream's filters and grouping state.
 */
#[Package('inventory')]
abstract class AbstractProductStreamBuilder
{
    abstract public function enrichCriteria(Criteria $criteria, string $id, Context $context): void;

    /**
     * Enriches the criteria of several product streams, so that an implementation can load all of those streams
     * together instead of one stream per criteria. Clone the enriched criteria if more than one place uses the same
     * stream, so that each place can set its own limit and sorting.
     *
     * @param array<string, Criteria> $criteriaByStreamId
     */
    public function enrichCriterias(array $criteriaByStreamId, Context $context): void
    {
        foreach ($criteriaByStreamId as $streamId => $criteria) {
            $this->enrichCriteria($criteria, $streamId, $context);
        }
    }
}
