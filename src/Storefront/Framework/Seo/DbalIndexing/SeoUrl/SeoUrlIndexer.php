<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\DbalIndexing\SeoUrl;

use function Flag\next741;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\SeoService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class SeoUrlIndexer implements IndexerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SeoService
     */
    private $seoService;

    /**
     * @var SalesChannelContextFactory
     */
    private $salesChannelContextFactory;

    /**
     * @var string
     */
    private $routeName;

    /**
     * @var EntityRepositoryInterface
     */
    private $entityRepository;

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        EventDispatcherInterface $eventDispatcher,
        SeoService $seoService,
        SalesChannelContextFactory $salesChannelContextFactory,
        string $routeName,
        EntityRepositoryInterface $entityRepository
    ) {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->eventDispatcher = $eventDispatcher;

        $this->seoService = $seoService;
        $this->salesChannelContextFactory = $salesChannelContextFactory;

        $this->routeName = $routeName;
        $this->entityRepository = $entityRepository;
    }

    abstract public function extractIds(EntityWrittenContainerEvent $event): array;

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        // skip if feature is disabled
        if (!next741()) {
            return;
        }
        $salesChannels = $this->getSalesChannels();

        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $context = $this->getSalesChannelContext($salesChannel->getId())->getContext();
            $iterator = new RepositoryIterator($this->entityRepository, $context);

            $this->eventDispatcher->dispatch(
                ProgressStartedEvent::NAME,
                new ProgressStartedEvent(
                    sprintf(
                        'Start indexing %s seo urls for sales channel %s',
                        $this->routeName, $salesChannel->getName()
                    ),
                    $iterator->getTotal()
                )
            );

            while ($ids = $iterator->fetchIds()) {
                $this->seoService->updateSeoUrls(
                    $salesChannel->getId(),
                    $this->routeName,
                    $ids,
                    $this->seoService->generateSeoUrls($salesChannel->getId(), $this->routeName, $ids)
                );

                $this->eventDispatcher->dispatch(
                    ProgressAdvancedEvent::NAME,
                    new ProgressAdvancedEvent(\count($ids))
                );
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent(sprintf(
                    'Finished indexing %s seo urls for application %s',
                    $this->routeName, $salesChannel->getName()
                ))
            );
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        // skip if feature is disabled
        if (!next741()) {
            return;
        }

        $salesChannels = $this->getSalesChannels();
        $ids = $this->extractIds($event);
        if (empty($ids)) {
            return;
        }

        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $this->seoService->updateSeoUrls(
                $salesChannel->getId(),
                $this->routeName,
                $ids,
                $this->seoService->generateSeoUrls($salesChannel->getId(), $this->routeName, $ids)
            );
        }
    }

    private function getSalesChannelContext(string $salesChannelId): SalesChannelContext
    {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->salesChannelRepository
            ->search(new Criteria([$salesChannelId]), Context::createDefaultContext())
            ->first();
        $options = $salesChannel->jsonSerialize();

        return $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannelId,
            $options
        );
    }

    private function getSalesChannels(): SalesChannelCollection
    {
        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository
            ->search(new Criteria(), Context::createDefaultContext())
            ->getEntities();

        return $salesChannels;
    }
}
