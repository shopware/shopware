<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\Exception\PluginPaymentMethodsDeleteRestrictionException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;

class PaymentMethodRepositoryDecorator implements EntityRepositoryInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $innerRepo;

    public function __construct(EntityRepositoryInterface $innerRepo)
    {
        $this->innerRepo = $innerRepo;
    }

    /**
     * @throws PluginPaymentMethodsDeleteRestrictionException
     */
    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        $affectedPaymentMethods = $this->search(new Criteria(array_column($ids, 'id')), $context);

        if ($affectedPaymentMethods->count() === 0) {
            return $this->innerRepo->delete($ids, $context);
        }

        /** @var PaymentMethodEntity $entity */
        foreach ($affectedPaymentMethods->getEntities() as $entity) {
            if ($entity->getPluginId()) {
                throw new PluginPaymentMethodsDeleteRestrictionException();
            }
        }

        return $this->innerRepo->delete($ids, $context);
    }

    public function internalDelete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        return $this->innerRepo->delete($ids, $context);
    }

    // Unchanged methods

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        return $this->innerRepo->aggregate($criteria, $context);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->innerRepo->searchIds($criteria, $context);
    }

    public function clone(string $id, Context $context, ?string $newId = null, ?CloneBehavior $behavior = null): EntityWrittenContainerEvent
    {
        return $this->innerRepo->clone($id, $context, $newId, $behavior);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->innerRepo->search($criteria, $context);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->innerRepo->update($data, $context);
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->innerRepo->upsert($data, $context);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->innerRepo->create($data, $context);
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->innerRepo->createVersion($id, $context, $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->innerRepo->merge($versionId, $context);
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->innerRepo->getDefinition();
    }
}
