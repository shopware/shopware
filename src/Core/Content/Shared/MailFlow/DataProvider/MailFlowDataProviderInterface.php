<?php declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow\DataProvider;

use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @template TEntity of Entity
 */
#[Package('after-sales')]
interface MailFlowDataProviderInterface
{
    public function getEntityName(): string;

    /**
     * Implementations should dispatch {@see MailFlowDataCriteriaEvent} when building the criteria
     * so provider-specific criteria can still be extended by listeners.
     */
    public function getCriteria(string $entityId, Context $context): Criteria;

    /**
     * @return TEntity|null
     */
    public function getData(string $entityId, Context $context): ?Entity;
}
