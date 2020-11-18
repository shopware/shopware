<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\SalesChannel;

use League\Flysystem\FilesystemInterface;
use Monolog\Logger;
use OpenApi\Annotations as OA;
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
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Storefront\Event\ProductExportContentTypeEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ExportController
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

    /**
     * @var SalesChannelContextFactory
     */
    private $contextFactory;

    public function __construct(
        ProductExporterInterface $productExportService,
        ProductExportFileHandlerInterface $productExportFileHandler,
        FilesystemInterface $fileSystem,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $productExportRepository,
        SalesChannelContextFactory $contextFactory
    ) {
        $this->productExportService = $productExportService;
        $this->productExportFileHandler = $productExportFileHandler;
        $this->fileSystem = $fileSystem;
        $this->eventDispatcher = $eventDispatcher;
        $this->productExportRepository = $productExportRepository;
        $this->contextFactory = $contextFactory;
    }

    /**
     * @Since("6.3.2.0")
     * @OA\Get(
     *      path="/product-export/{accessKey}/{fileName}",
     *      summary="Export product export",
     *      operationId="readProductExport",
     *      tags={"Store API", "Product"},
     *      @OA\Parameter(name="accessKey", description="Access Key", @OA\Schema(type="string"), in="path", required=true),
     *      @OA\Parameter(name="fileName", description="File Name", @OA\Schema(type="string"), in="path", required=true),
     *      @OA\Response(
     *          response="200",
     *          description=""
     *     )
     * )
     * @Route("/store-api/product-export/{accessKey}/{fileName}", name="store-api.product.export", methods={"GET"}, defaults={"auth_required"=false})
     */
    public function index(Request $request): Response
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('fileName', $request->get('fileName')))
            ->addFilter(new EqualsFilter('accessKey', $request->get('accessKey')))
            ->addFilter(new EqualsFilter('salesChannel.active', true))
            ->addAssociation('salesChannelDomain');

        /** @var ProductExportEntity|null $productExport */
        $productExport = $this->productExportRepository->search($criteria, Context::createDefaultContext())->first();

        if ($productExport === null) {
            $exportNotFoundException = new ExportNotFoundException(null, $request->get('fileName'));
            $this->logException(Context::createDefaultContext(), $exportNotFoundException);

            throw $exportNotFoundException;
        }

        $context = $this->contextFactory->create('', $productExport->getSalesChannelDomain()->getSalesChannelId());

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
