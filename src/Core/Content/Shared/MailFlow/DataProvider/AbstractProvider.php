<?php declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow\DataProvider;

use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
abstract class AbstractProvider
{
    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly ContainerInterface $container,
    ) {
    }

    abstract public function getEntityName(): string;

    public function getCriteria(string $entityId, Context $context): Criteria
    {
        $criteria = $this->constructCriteria($entityId);

        $event = new MailFlowDataCriteriaEvent(
            $this->getEntityName(),
            $criteria,
            $context,
        );

        $this->eventDispatcher->dispatch($event, $event->getName());

        return $criteria;
    }

    public function getData(string $entityId, Context $context): ?Entity
    {
        $criteria = $this->getCriteria($entityId, $context);

        return $this->getRepository()->search($criteria, $context)->getEntities()->get($entityId);
    }

    // @phpstan-ignore missingType.generics
    protected function getRepository(): EntityRepository
    {
        $repository = $this->container->get($this->getEntityName() . '.repository');

        \assert($repository instanceof EntityRepository);

        return $repository;
    }

    abstract protected function constructCriteria(string $entityId): Criteria;
}
