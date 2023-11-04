<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Monolog\Level;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Exception\ExportInvalidException;
use Shopware\Core\Content\ProductExport\Exception\ExportNotFoundException;
use Shopware\Core\Content\ProductExport\Exception\ExportNotGeneratedException;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductExport\Struct\ProductExportResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('sales-channel')]
class ProductExporter implements ProductExporterInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $productExportRepository,
        private readonly ProductExportGeneratorInterface $productExportGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProductExportFileHandlerInterface $productExportFileHandler
    ) {
    }

    public function export(
        SalesChannelContext $context,
        ExportBehavior $behavior,
        ?string $productExportId = null
    ): void {
        $criteria = new Criteria();
        if ($productExportId !== null) {
            $criteria = new Criteria(array_filter([$productExportId]));
        }

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

        /** @var ProductExportCollection $productExports */
        $productExports = $this->productExportRepository->search($criteria, $context->getContext());

        if ($productExports->count() === 0) {
            $exportNotFoundException = new ExportNotFoundException($productExportId);
            $this->logException($context->getContext(), $exportNotFoundException);

            throw $exportNotFoundException;
        }

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
        if (!$result instanceof ProductExportResult) {
            $exportNotGeneratedException = new ExportNotGeneratedException();
            $this->logException($context->getContext(), $exportNotGeneratedException);

            throw $exportNotGeneratedException;
        }

        if ($result->hasErrors()) {
            $exportInvalidException = new ExportInvalidException($productExport, $result->getErrors());
            $this->logException($context->getContext(), $exportInvalidException);

            throw $exportInvalidException;
        }

        $writeProductExportSuccessful = $this->productExportFileHandler->writeProductExportContent(
            $result->getContent(),
            $filePath
        );

        if (!$writeProductExportSuccessful) {
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
            Level::Warning,
            $exception
        );

        $this->eventDispatcher->dispatch($loggingEvent);
    }
}
