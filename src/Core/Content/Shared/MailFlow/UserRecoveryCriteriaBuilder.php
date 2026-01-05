<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow;

use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class UserRecoveryCriteriaBuilder
{
    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function getCriteria(string $entityId, Context $context): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociation('user');

        $event = new MailFlowDataCriteriaEvent(
            UserRecoveryDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        return $criteria;
    }
}
