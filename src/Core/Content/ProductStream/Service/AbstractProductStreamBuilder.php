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
     * Enriches the criteria of several product streams, so that an implementation can load the streams of all of them
     * together instead of one stream per criteria. A stream can carry more than one criteria, because the limit and
     * the sorting of a criteria belong to the place it is used, not to the stream.
     *
     * @param array<string, list<Criteria>> $criteriaByStreamId
     */
    public function enrichCriterias(array $criteriaByStreamId, Context $context): void
    {
        foreach ($criteriaByStreamId as $streamId => $criterias) {
            foreach ($criterias as $criteria) {
                $this->enrichCriteria($criteria, $streamId, $context);
            }
        }
    }
}
