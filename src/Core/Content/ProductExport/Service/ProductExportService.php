<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ProductExport\Exception\EmptyExportException;
use Shopware\Core\Content\ProductExport\Exception\ExportNotFoundException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductStream\Service\ProductStreamServiceInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    /** @var string */
    private $directoryName;

    /** @var int */
    private $readBufferSize;

    public function __construct(
        EntityRepositoryInterface $productExportRepository,
        ProductStreamServiceInterface $productStreamService,
        ProductExportRenderServiceInterface $productExportRender,
        FilesystemInterface $fileSystem,
        string $directoryName,
        int $readBufferSize
    ) {
        $this->productExportRepository = $productExportRepository;
        $this->productStreamService = $productStreamService;
        $this->productExportRender = $productExportRender;
        $this->fileSystem = $fileSystem;
        $this->directoryName = $directoryName;
        $this->readBufferSize = $readBufferSize;
    }

    public function generate(SalesChannelContext $context, ?string $productExportId = null): void
    {
        $criteria = new Criteria(array_filter([$productExportId]));
        $criteria
            ->addAssociation('productStream.filters.queries');

        $productExports = $this->productExportRepository->search($criteria, $context->getContext());

        if ($productExports->count() === 0) {
            throw new ExportNotFoundException($productExportId);
        }

        /** @var ProductExportEntity $productExport */
        foreach ($productExports as $productExport) {
            $this->generateExport($productExport, $context);
        }
    }

    public function generateExport(ProductExportEntity $productExport, SalesChannelContext $context): void
    {
        $offset = 0;
        $tmpFile = tempnam(sys_get_temp_dir(), 'productexport');

        do {
            $products = $this->productStreamService->getProducts(
                $productExport->getProductStream(),
                $context,
                $offset,
                $this->readBufferSize
            );

            if ($products->getTotal() === 0) {
                throw new EmptyExportException($productExport->getId());
            }

            $offset += $products->count();
            $content = $this->productExportRender->renderBody($productExport, $products->getEntities());

            // Can't use filesystem service because of the necessary flag
            file_put_contents($tmpFile, $content, FILE_APPEND);
        } while ($offset < $products->getTotal());

        $content
            = $this->productExportRender->renderHeader($productExport)
            . file_get_contents($tmpFile)
            . $this->productExportRender->renderFooter($productExport);

        $this->fileSystem->write(
            $this->getFilePath($productExport),
            $this->convertEncoding($content, $productExport->getEncoding())
        );
    }

    public function convertEncoding(string $content, string $encoding): string
    {
        return mb_convert_encoding($content, $encoding);
    }

    public function getFilePath(ProductExportEntity $productExportEntity): string
    {
        return $this->getDirectory() . sprintf(
                '/%s.%s',
                $productExportEntity->getFileName(),
                $productExportEntity->getFileFormat()
            );
    }

    public function getDirectory(): string
    {
        if (!$this->fileSystem->has($this->directoryName)) {
            $this->fileSystem->createDir($this->directoryName);
        }

        return $this->directoryName;
    }
}
