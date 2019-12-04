<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use League\Flysystem\FilesystemInterface;
use Monolog\Logger;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Exception\ExportNotFoundException;
use Shopware\Core\Content\ProductExport\Exception\ExportNotGeneratedException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Service\ProductExporterInterface;
use Shopware\Core\Content\ProductExport\Service\ProductExportFileHandlerInterface;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\ProductExportContentTypeEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ProductExportController extends StorefrontController
{
    /** @var ProductExporterInterface */
    private $productExportService;

    /** @var FilesystemInterface */
    private $fileSystem;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var EntityRepositoryInterface */
    private $productExportRepository;

    /** @var ProductExportFileHandlerInterface */
    private $productExportFileHandler;

    public function __construct(
        ProductExporterInterface $productExportService,
        ProductExportFileHandlerInterface $productExportFileHandler,
        FilesystemInterface $fileSystem,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $productExportRepository
    ) {
        $this->productExportService = $productExportService;
        $this->productExportFileHandler = $productExportFileHandler;
        $this->fileSystem = $fileSystem;
        $this->eventDispatcher = $eventDispatcher;
        $this->productExportRepository = $productExportRepository;
    }

    /**
     * @Route("/export/{accessKey}/{fileName}", name="frontend.export", methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('fileName', $request->get('fileName')))
            ->addFilter(new EqualsFilter('accessKey', $request->get('accessKey')))
            ->addFilter(new EqualsFilter('salesChannel.active', true))
            ->addFilter(
                new MultiFilter(
                    'OR',
                    [
                        new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
                        new EqualsFilter('salesChannelDomain.salesChannel.id', $context->getSalesChannel()->getId()),
                    ]
                )
            );

        /** @var ProductExportEntity|null $productExport */
        $productExport = $this->productExportRepository->search($criteria, $context->getContext())->first();

        if ($productExport === null) {
            $exportNotFoundException = new ExportNotFoundException(null, $request->get('fileName'));
            $this->logException($context->getContext(), $exportNotFoundException);

            throw $exportNotFoundException;
        }

        $this->productExportService->export($context, new ExportBehavior(), $productExport->getId());
        $filePath = $this->productExportFileHandler->getFilePath($productExport);
        if (!$this->fileSystem->has($filePath)) {
            $exportNotGeneratedException = new ExportNotGeneratedException();
            $this->logException($context->getContext(), $exportNotGeneratedException);

            throw $exportNotGeneratedException;
        }

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

    private function logException(
        Context $context,
        \Exception $exception
    ): void {
        $loggingEvent = new ProductExportLoggingEvent(
            $context,
            $exception->getMessage(),
            Logger::ERROR,
            $exception
        );

        $this->eventDispatcher->dispatch($loggingEvent);
    }
}
