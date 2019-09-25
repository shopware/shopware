<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\ScheduledTask;

use Shopware\Core\Content\ProductExport\Service\ProductExporterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class ProductExportPartialGenerationHandler extends AbstractMessageHandler
{
    /** @var ProductExporterInterface */
    private $productExporter;

    /** @var SalesChannelContextFactory */
    private $salesChannelContextFactory;

    /** @var EntityRepository */
    private $salesChannelRepository;

    public function __construct(
        ProductExporterInterface $productExporter,
        SalesChannelContextFactory $salesChannelContextFactory,
        EntityRepository $salesChannelRepository
    ) {
        $this->productExporter = $productExporter;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public static function getHandledMessages(): iterable
    {
        return [
            ProductExportPartialGeneration::class,
        ];
    }

    /**
     * @param ProductExportPartialGeneration $productExportPartialGeneration
     */
    public function handle($productExportPartialGeneration): void
    {
        if ($productExportPartialGeneration->getOffset() === 0) {
            // Create file
        }
    }
}
