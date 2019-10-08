<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\ScheduledTask;

use Shopware\Core\Content\ProductExport\Exception\ExportNotFoundException;
use Shopware\Core\Content\ProductExport\Service\ProductExporterInterface;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class ProductExportGenerateTaskHandler extends ScheduledTaskHandler
{
    /** @var ProductExporterInterface */
    private $productExporter;

    /** @var SalesChannelContextFactory */
    private $salesChannelContextFactory;

    /** @var EntityRepository */
    private $salesChannelRepository;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        ProductExporterInterface $productExporter,
        SalesChannelContextFactory $salesChannelContextFactory,
        EntityRepository $salesChannelRepository
    ) {
        parent::__construct($scheduledTaskRepository);

        $this->productExporter = $productExporter;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->salesChannelRepository = $salesChannelRepository;
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

        foreach ($salesChannelIds->getIds() as $salesChannelId) {
            $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);

            try {
                $this->productExporter->export($salesChannelContext, new ExportBehavior());
            } catch (ExportNotFoundException $_) {
                // Ignore when storefront has no defined exports
            }
        }
    }
}
