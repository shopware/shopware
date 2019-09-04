<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ProductExport\Exception\EmptyExportException;
use Shopware\Core\Content\ProductExport\Exception\ExportNotFoundException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductStream\Service\ProductStreamServiceInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductExportService implements ProductExportServiceInterface
{
    /** @var EntityRepositoryInterface */
    private $productExportRepository;

    /** @var ProductStreamServiceInterface */
    private $productStreamService;

    /** @var ProductExportRenderServiceInterface */
    private $productExportRender;

    /** @var FilesystemInterface */
    private $fileSystem;

    /** @var ProductExportFileServiceInterface */
    private $productExportFileService;

    /** @var int */
    private $readBufferSize;

    public function __construct(
        EntityRepositoryInterface $productExportRepository,
        ProductStreamServiceInterface $productStreamService,
        ProductExportRenderServiceInterface $productExportRender,
        FilesystemInterface $fileSystem,
        ProductExportFileServiceInterface $productExportFileService,
        int $readBufferSize
    ) {
        $this->productExportRepository = $productExportRepository;
        $this->productStreamService = $productStreamService;
        $this->productExportRender = $productExportRender;
        $this->fileSystem = $fileSystem;
        $this->productExportFileService = $productExportFileService;
        $this->readBufferSize = $readBufferSize;
    }

    public function generate(
        SalesChannelContext $context,
        ?string $productExportId = null,
        bool $includeInactive = false,
        bool $ignoreCache = false
    ): void {
        $criteria = new Criteria(array_filter([$productExportId]));
        $criteria
            ->addAssociation('salesChannel')
            ->addAssociation('salesChannelDomain.salesChannel')
            ->addAssociation('productStream.filters.queries')
            ->addFilter(new MultiFilter(
                'OR',
                [
                    new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
                    new EqualsFilter('product_export.salesChannelDomain.salesChannel.id', $context->getSalesChannel()->getId()),
                ]
            ))
        ;

        if (!$includeInactive) {
            $criteria->addFilter(new EqualsFilter('product_export.salesChannel.active', true));
        }

        $productExports = $this->productExportRepository->search($criteria, $context->getContext());

        if ($productExports->count() === 0) {
            throw new ExportNotFoundException($productExportId);
        }

        /** @var ProductExportEntity $productExport */
        foreach ($productExports as $productExport) {
            $this->generateExport($productExport, $context, $ignoreCache);
        }
    }

    public function generateExport(
        ProductExportEntity $productExport,
        SalesChannelContext $context,
        bool $ignoreCache = false
    ): void {
        if (!$ignoreCache && $this->isValidFile($productExport)) {
            return;
        }

        $offset = 0;
        $tmpFile = tempnam(sys_get_temp_dir(), 'productexport');

        do {
            $products = $this->productStreamService->getProductsById(
                $productExport->getProductStream()->getId(),
                $context,
                $offset,
                $this->readBufferSize
            );

            if ($products->getTotal() === 0) {
                throw new EmptyExportException($productExport->getId());
            }

            $offset += $products->count();

            $content = $this->fileSystem->has($tmpFile)
                ? $this->fileSystem->read($tmpFile)
                : '';

            $content .= $this->productExportRender->renderBody($productExport, $products->getEntities(), $context);

            $this->fileSystem->put($tmpFile, $content);
        } while ($offset < $products->getTotal());

        $content
            = $this->productExportRender->renderHeader($productExport, $context)
            . $this->fileSystem->read($tmpFile)
            . $this->productExportRender->renderFooter($productExport, $context);

        $filePath = $this->productExportFileService->getFilePath($productExport);

        if ($this->fileSystem->has($filePath)) {
            $this->fileSystem->delete($filePath);
        }

        $this->productExportRepository->update(
            [
                [
                    'id' => $productExport->getId(),
                    'lastGeneration' => new \DateTime(),
                ],
            ],
            $context->getContext()
        );

        $this->fileSystem->write(
            $filePath,
            $this->convertEncoding($content, $productExport->getEncoding())
        );
    }

    public function convertEncoding(string $content, string $encoding): string
    {
        return mb_convert_encoding($content, $encoding);
    }

    private function isValidFile(ProductExportEntity $productExportEntity): bool
    {
        $filePath = $this->productExportFileService->getFilePath($productExportEntity);

        if (!$this->fileSystem->has($filePath)) {
            return false;
        }

        return $productExportEntity->isGenerateByCronjob()
            || (!$productExportEntity->isGenerateByCronjob() && !$this->isCacheExpired($productExportEntity));
    }

    private function isCacheExpired(ProductExportEntity $productExportEntity): bool
    {
        if ($productExportEntity->getLastGeneration() === null) {
            return true;
        }

        $expireTimestamp = $productExportEntity->getLastGeneration()->getTimestamp(
            ) + $productExportEntity->getInterval();

        return (new \DateTime())->getTimestamp() > $expireTimestamp;
    }
}
