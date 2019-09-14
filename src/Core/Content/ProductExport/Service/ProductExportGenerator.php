<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Monolog\Logger;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductExport\Event\ProductExportChangeEncodingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopware\Core\Content\ProductExport\Exception\EmptyExportException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductExport\Struct\ProductExportResult;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\SalesChannelRepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductExportGenerator implements ProductExportGeneratorInterface
{
    /** @var ProductStreamBuilderInterface */
    private $productStreamBuilder;

    /** @var int */
    private $readBufferSize;

    /** @var SalesChannelRepositoryInterface */
    private $productRepository;

    /** @var ProductExportRendererInterface */
    private $productExportRender;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var ProductExportValidatorInterface */
    private $productExportValidator;

    public function __construct(
        ProductStreamBuilderInterface $productStreamBuilder,
        SalesChannelRepositoryInterface $productRepository,
        ProductExportRendererInterface $productExportRender,
        EventDispatcherInterface $eventDispatcher,
        ProductExportValidatorInterface $productExportValidator,
        int $readBufferSize
    ) {
        $this->productStreamBuilder = $productStreamBuilder;
        $this->productRepository = $productRepository;
        $this->productExportRender = $productExportRender;
        $this->eventDispatcher = $eventDispatcher;
        $this->productExportValidator = $productExportValidator;
        $this->readBufferSize = $readBufferSize;
    }

    public function generate(ProductExportEntity $productExport, ExportBehavior $exportBehavior, SalesChannelContext $context): ProductExportResult
    {
        $filters = $this->productStreamBuilder->buildFilters(
            $productExport->getProductStreamId(),
            $context->getContext()
        );

        $criteria = new Criteria();
        $criteria
            ->addFilter(...$filters)
            ->setLimit($this->readBufferSize)
            ->addAssociation('manufacturer')
            ->addAssociation('media')
            ->addAssociation('categories');

        $iterator = new SalesChannelRepositoryIterator($this->productRepository, $context, $criteria);

        $total = $iterator->getTotal();
        if ($total === 0) {
            $exception = new EmptyExportException($productExport->getId());

            $loggingEvent = new ProductExportLoggingEvent(
                $context->getContext(),
                $exception->getMessage(),
                Logger::WARNING,
                $exception
            );

            $this->eventDispatcher->dispatch($loggingEvent);

            throw $exception;
        }

        $content = $this->productExportRender->renderHeader($productExport, $context);

        $productContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderBodyContextEvent(
                [
                    'productExport' => $productExport,
                    'context' => $context,
                ]
            )
        );
        while ($productResult = $iterator->fetch()) {
            /** @var ProductEntity $product */
            foreach ($productResult->getEntities() as $product) {
                $data = $productContext->getContext();
                $data['product'] = $product;

                $content .= $this->productExportRender->renderBody($productExport, $context, $data);
            }

            if ($exportBehavior->preview()) {
                break;
            }
        }
        $content .= $this->productExportRender->renderFooter($productExport, $context);

        /** @var ProductExportChangeEncodingEvent $encodingEvent */
        $encodingEvent = $this->eventDispatcher->dispatch(
            new ProductExportChangeEncodingEvent($productExport, $content, mb_convert_encoding($content, $productExport->getEncoding()))
        );

        return new ProductExportResult($encodingEvent->getEncodedContent(), $this->productExportValidator->validate($productExport, $encodingEvent->getEncodedContent()));
    }
}
