<?php declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
class MailFlowDataCriteriaEvent extends Event implements ShopwareEvent, GenericEvent
{
    public function __construct(
        public readonly string $entityName,
        public readonly Criteria $criteria,
        private readonly Context $context,
    ) {
    }

    public function getName(): string
    {
        return 'mail-flow.data.' . $this->entityName . '.criteria.event';
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
