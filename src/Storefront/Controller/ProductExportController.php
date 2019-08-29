<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Service\ProductExportFileServiceInterface;
use Shopware\Core\Content\ProductExport\Service\ProductExportServiceInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\ProductExportContentTypeEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductExportController extends StorefrontController
{
    /** @var ProductExportServiceInterface */
    private $productExportService;

    /** @var ProductExportFileServiceInterface */
    private $productExportFileService;

    /** @var FilesystemInterface */
    private $fileSystem;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(
        ProductExportServiceInterface $productExportService,
        ProductExportFileServiceInterface $productExportFileService,
        FilesystemInterface $fileSystem,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productExportService = $productExportService;
        $this->productExportFileService = $productExportFileService;
        $this->fileSystem = $fileSystem;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/export/{accessKey}/{fileName}", name="frontend.export", methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $productExport = $this->productExportService->get(
            $request->get('fileName'),
            $request->get('accessKey'),
            $context
        );

        $filePath = $this->productExportFileService->getFilePath($productExport);
        $content = $this->fileSystem->read($filePath);

        $contentType = $this->getContentType($productExport->getFileFormat());
        $encoding = $productExport->getEncoding();

        return (new Response($content, 200, ['Content-Type' => $contentType . ';charset=' . $encoding]))
            ->setCharset($encoding);
    }

    private function getContentType(string $fileFormat): string
    {
        $contentType = 'text/plain';

        switch ($fileFormat) {
            case ProductExportEntity::FILE_FORMAT_CSV:
                $contentType = 'text/csv';
                break;
            case ProductExportEntity::FILE_FORMAT_XML:
                $contentType = 'text/xml';
                break;
        }

        $event = new ProductExportContentTypeEvent($fileFormat, $contentType);
        $this->eventDispatcher->dispatch($event);

        return $event->getContentType();
    }
}
