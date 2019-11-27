<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Monolog\Logger;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Exception\ExportInvalidException;
use Shopware\Core\Content\ProductExport\Exception\ExportNotFoundException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductExporter implements ProductExporterInterface
{
    /** @var EntityRepositoryInterface */
    private $productExportRepository;

    /** @var ProductExportGeneratorInterface */
    private $productExportGenerator;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var ProductExportFileHandlerInterface */
    private $productExportFileHandler;

    public function __construct(
        EntityRepositoryInterface $productExportRepository,
        ProductExportGeneratorInterface $productExportGenerator,
        EventDispatcherInterface $eventDispatcher,
        ProductExportFileHandlerInterface $productExportFileHandler
    ) {
        $this->productExportRepository = $productExportRepository;
        $this->productExportGenerator = $productExportGenerator;
        $this->eventDispatcher = $eventDispatcher;
        $this->productExportFileHandler = $productExportFileHandler;
    }

    public function export(
        SalesChannelContext $context,
        ExportBehavior $behavior,
        ?string $productExportId = null
    ): void {
        $criteria = new Criteria(array_filter([$productExportId]));
        $criteria
            ->addAssociation('salesChannel')
            ->addAssociation('salesChannelDomain.salesChannel')
            ->addAssociation('salesChannelDomain.language.locale')
            ->addAssociation('productStream.filters.queries')
            ->addFilter(
                new MultiFilter(
                    'OR',
                    [
                        new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
                        new EqualsFilter('salesChannelDomain.salesChannel.id', $context->getSalesChannel()->getId()),
                    ]
                )
            );

        if (!$behavior->includeInactive()) {
            $criteria->addFilter(new EqualsFilter('salesChannel.active', true));
        }

        $productExports = $this->productExportRepository->search($criteria, $context->getContext());

        if ($productExports->count() === 0) {
            $exportNotFoundException = new ExportNotFoundException($productExportId);
            $this->logException($context->getContext(), $exportNotFoundException);

            throw $exportNotFoundException;
        }

        /** @var ProductExportEntity $productExport */
        foreach ($productExports as $productExport) {
            $this->createFile($productExport, $context, $behavior);
        }
    }

    private function createFile(
        ProductExportEntity $productExport,
        SalesChannelContext $context,
        ExportBehavior $exportBehavior
    ): void {
        $filePath = $this->productExportFileHandler->getFilePath($productExport);

        if ($this->productExportFileHandler->isValidFile(
            $filePath,
            $exportBehavior,
            $productExport
        )) {
            return;
        }
        $result = $this->productExportGenerator->generate($productExport, $exportBehavior);

        if ($result->hasErrors()) {
            $exportInvalidException = new ExportInvalidException($productExport, $result->getErrors());
            $this->logException($context->getContext(), $exportInvalidException);

            throw $exportInvalidException;
        }

        $writeProductExportSuccesful = $this->productExportFileHandler->writeProductExportContent(
            $result->getContent(),
            $filePath
        );

        if (!$writeProductExportSuccesful) {
            return;
        }

        $this->productExportRepository->update(
            [
                [
                    'id' => $productExport->getId(),
                    'generatedAt' => new \DateTime(),
                ],
            ],
            $context->getContext()
        );
    }

    private function logException(Context $context, \Exception $exception): void
    {
        $loggingEvent = new ProductExportLoggingEvent(
            $context,
            $exception->getMessage(),
            Logger::WARNING,
            $exception
        );

        $this->eventDispatcher->dispatch($loggingEvent);
    }
}
