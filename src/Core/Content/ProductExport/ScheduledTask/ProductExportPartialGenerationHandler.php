<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\ScheduledTask;

use Shopware\Core\Content\ProductExport\Service\ProductExporterInterface;
use Shopware\Core\Content\ProductExport\Service\ProductExportFileHandlerInterface;
use Shopware\Core\Content\ProductExport\Service\ProductExportGeneratorInterface;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\Framework\Routing\Exception\SalesChannelNotFoundException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Symfony\Component\Messenger\MessageBusInterface;

class ProductExportPartialGenerationHandler extends AbstractMessageHandler
{
    /** @var SalesChannelContextFactory */
    private $salesChannelContextFactory;

    /** @var EntityRepository */
    private $salesChannelRepository;

    /** @var EntityRepository */
    private $productExportRepository;

    /** @var ProductExportGeneratorInterface */
    private $productExportGenerator;

    /** @var int */
    private $readBufferSize;

    /** @var MessageBusInterface */
    private $messageBus;

    /** @var ProductExportFileHandlerInterface */
    private $productExportFileHandler;

    public function __construct(
        ProductExportGeneratorInterface $productExportGenerator,
        SalesChannelContextFactory $salesChannelContextFactory,
        EntityRepository $salesChannelRepository,
        EntityRepository $productExportRepository,
        ProductExportFileHandlerInterface $productExportFileHandler,
        MessageBusInterface $messageBus,
        int $readBufferSize
    ) {
        $this->productExportGenerator = $productExportGenerator;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->productExportRepository = $productExportRepository;
        $this->readBufferSize = $readBufferSize;
        $this->messageBus = $messageBus;
        $this->productExportFileHandler = $productExportFileHandler;
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
        $generateHeader = false;
        $generateFooter = false;

        if ($productExportPartialGeneration->getOffset() === 0) {
            $generateHeader = true;
        }

        $criteria = new Criteria(array_filter([$productExportPartialGeneration->getProductExportId()]));
        $criteria
            ->addAssociation('salesChannel')
            ->addAssociation('salesChannelDomain.salesChannel')
            ->addAssociation('salesChannelDomain.language.locale')
            ->addAssociation('productStream.filters.queries');

        $salesChannelContext = $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $productExportPartialGeneration->getSalesChannelId()
        );

        if ($salesChannelContext->getSalesChannel()->getTypeId() !== Defaults::SALES_CHANNEL_TYPE_STOREFRONT) {
            throw new SalesChannelNotFoundException();
        }

        $productExports = $this->productExportRepository->search($criteria, $salesChannelContext->getContext());

        if ($productExports->count() === 0) {
            return;
        }

        $exportBehavior = new ExportBehavior(
            false,
            false,
            false,
            $generateHeader,
            $generateFooter,
            $productExportPartialGeneration->getOffset()
        );

        $productExport = $productExports->first();
        $exportResult  = $this->productExportGenerator->generate(
            $productExport,
            $exportBehavior
        );

        $filePath = $this->productExportFileHandler->getFilePath($productExport, true);
        $this->productExportFileHandler->writeProductExportResult(
            $exportResult,
            $filePath,
            $productExportPartialGeneration->getOffset() > 0
        );

        if ($productExportPartialGeneration->getOffset() + $this->readBufferSize < $exportResult->getTotal()) {
            $this->messageBus->dispatch(
                new ProductExportPartialGeneration(
                    $productExportPartialGeneration->getProductExportId(),
                    $productExportPartialGeneration->getSalesChannelId(),
                    $productExportPartialGeneration->getOffset() + $this->readBufferSize
                )
            );
        }
    }
}
