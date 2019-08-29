<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ProductExport\Exception\ExportNotFoundException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductExportService implements ProductExportServiceInterface
{
    /** @var EntityRepositoryInterface */
    private $repository;

    /** @var FilesystemInterface */
    private $fileSystem;

    /** @var ProductExportGenerateServiceInterface */
    private $productExportGenerateService;

    /** @var ProductExportFileServiceInterface */
    private $productExportFileService;

    public function __construct(
        EntityRepositoryInterface $repository,
        FilesystemInterface $fileSystem,
        ProductExportGenerateServiceInterface $productExportGenerateService,
        ProductExportFileServiceInterface $productExportFileService
    ) {
        $this->repository = $repository;
        $this->fileSystem = $fileSystem;
        $this->productExportGenerateService = $productExportGenerateService;
        $this->productExportFileService = $productExportFileService;
    }

    public function get(string $fileName, string $accessKey, SalesChannelContext $salesChannelContext): ProductExportEntity
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('fileName', $fileName))
            ->addFilter(new EqualsFilter('accessKey', $accessKey))
            ->addAssociation('productStream')
        ;

        /** @var ProductExportEntity|null $productExport */
        $productExport = $this->repository->search($criteria, $salesChannelContext->getContext())->first();

        if ($productExport === null) {
            throw new ExportNotFoundException(null, $fileName);
        }

        if (!$this->isValidFile($productExport)) {
            $this->productExportGenerateService->generateExport($productExport, $salesChannelContext);
        }

        return $productExport;
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

        $expireTimestamp = $productExportEntity->getLastGeneration()->getTimestamp() + $productExportEntity->getInterval();

        return (new \DateTime())->getTimestamp() > $expireTimestamp;
    }
}
