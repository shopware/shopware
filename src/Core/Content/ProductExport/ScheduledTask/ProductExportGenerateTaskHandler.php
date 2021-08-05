<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\ScheduledTask;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\Messenger\MessageBusInterface;

class ProductExportGenerateTaskHandler extends ScheduledTaskHandler
{
    /**
     * @var AbstractSalesChannelContextFactory
     */
    private $salesChannelContextFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productExportRepository;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $productExportRepository,
        MessageBusInterface $messageBus
    ) {
        parent::__construct($scheduledTaskRepository);

        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->productExportRepository = $productExportRepository;
        $this->messageBus = $messageBus;
    }

    public static function getHandledMessages(): iterable
    {
        return [
            ProductExportGenerateTask::class,
        ];
    }

    public function run(): void
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
            ->addFilter(new EqualsFilter('active', true));

        $salesChannelIds = $this->salesChannelRepository->searchIds($criteria, Context::createDefaultContext());

        /** @var string $salesChannelId */
        foreach ($salesChannelIds->getIds() as $salesChannelId) {
            $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);

            $criteria = new Criteria();
            $criteria
                ->addAssociation('salesChannel')
                ->addAssociation('salesChannelDomain.salesChannel')
                ->addAssociation('salesChannelDomain.language.locale')
                ->addAssociation('productStream.filters.queries')
                ->addFilter(new EqualsFilter('generateByCronjob', true))
                ->addFilter(
                    new MultiFilter(
                        'OR',
                        [
                            new EqualsFilter('storefrontSalesChannelId', $salesChannelContext->getSalesChannel()->getId()),
                            new EqualsFilter('salesChannelDomain.salesChannel.id', $salesChannelContext->getSalesChannel()->getId()),
                        ]
                    )
                );

            $productExports = $this->productExportRepository->search($criteria, $salesChannelContext->getContext());

            if ($productExports->count() === 0) {
                continue;
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

            /** @var ProductExportEntity $productExport */
            foreach ($productExports as $productExport) {
                // Make sure the product export is due to be exported
                if ($productExport->getGeneratedAt() !== null) {
                    if ($now->getTimestamp() - $productExport->getGeneratedAt()->getTimestamp() < $productExport->getInterval()) {
                        continue;
                    }
                }
                $this->messageBus->dispatch(new ProductExportPartialGeneration($productExport->getId(), $salesChannelId));
            }
        }
    }
}
