<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;

interface EntityRepositoryInterface
{
    public function getDefinition(): EntityDefinition;

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection;

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult;

    /**
     * @param CloneBehavior|null $behavior - @deprecated tag:v6.4.0 - Will be implemented in 6.4.0
     */
    public function clone(string $id, Context $context, ?string $newId = null/*, CloneBehavior $behavior = null*/): EntityWrittenContainerEvent;

    public function search(Criteria $criteria, Context $context): EntitySearchResult;

    public function update(array $data, Context $context): EntityWrittenContainerEvent;

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent;

    public function create(array $data, Context $context): EntityWrittenContainerEvent;

    public function delete(array $data, Context $context): EntityWrittenContainerEvent;

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string;

    public function merge(string $versionId, Context $context): void;
}
