<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

interface EntityRepositoryInterface
{
    public function getDefinition(): EntityDefinition;

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection;

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult;

    public function clone(string $id, Context $context, ?string $newId = null): EntityWrittenContainerEvent;

    public function search(Criteria $criteria, Context $context): EntitySearchResult;

    public function update(array $data, Context $context): EntityWrittenContainerEvent;

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent;

    public function create(array $data, Context $context): EntityWrittenContainerEvent;

    public function delete(array $data, Context $context): EntityWrittenContainerEvent;

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string;

    public function merge(string $versionId, Context $context): void;
}
