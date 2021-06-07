<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductExport\Event\ProductExportChangeEncodingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportProductCriteriaEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopware\Core\Content\ProductExport\Exception\EmptyExportException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductExport\Struct\ProductExportResult;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\SalesChannelRepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductExportGenerator implements ProductExportGeneratorInterface
{
    /**
     * @var ProductStreamBuilderInterface
     */
    private $productStreamBuilder;

    /**
     * @var int
     */
    private $readBufferSize;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductExportRendererInterface
     */
    private $productExportRender;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductExportValidatorInterface
     */
    private $productExportValidator;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $salesChannelContextService;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        ProductStreamBuilderInterface $productStreamBuilder,
        SalesChannelRepositoryInterface $productRepository,
        ProductExportRendererInterface $productExportRender,
        EventDispatcherInterface $eventDispatcher,
        ProductExportValidatorInterface $productExportValidator,
        SalesChannelContextServiceInterface $salesChannelContextService,
        Translator $translator,
        SalesChannelContextPersister $contextPersister,
        Connection $connection,
        int $readBufferSize
    ) {
        $this->productStreamBuilder = $productStreamBuilder;
        $this->productRepository = $productRepository;
        $this->productExportRender = $productExportRender;
        $this->eventDispatcher = $eventDispatcher;
        $this->productExportValidator = $productExportValidator;
        $this->salesChannelContextService = $salesChannelContextService;
        $this->translator = $translator;
        $this->contextPersister = $contextPersister;
        $this->connection = $connection;
        $this->readBufferSize = $readBufferSize;
    }

    public function generate(ProductExportEntity $productExport, ExportBehavior $exportBehavior): ?ProductExportResult
    {
        $contextToken = Uuid::randomHex();
        $this->contextPersister->save(
            $contextToken,
            [
                SalesChannelContextService::CURRENCY_ID => $productExport->getCurrencyId(),
            ],
            $productExport->getSalesChannelId()
        );

        $context = $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters(
                $productExport->getStorefrontSalesChannelId(),
                $contextToken,
                $productExport->getSalesChannelDomain()->getLanguageId(),
                $productExport->getSalesChannelDomain()->getCurrencyId() ?? $productExport->getCurrencyId()
            )
        );

        $this->translator->injectSettings(
            $productExport->getStorefrontSalesChannelId(),
            $productExport->getSalesChannelDomain()->getLanguageId(),
            $productExport->getSalesChannelDomain()->getLanguage()->getLocaleId(),
            $context->getContext()
        );

        $filters = $this->productStreamBuilder->buildFilters(
            $productExport->getProductStreamId(),
            $context->getContext()
        );

        $criteria = new Criteria();
        $criteria->setTitle('product-export::products');

        $criteria
            ->addFilter(...$filters)
            ->setOffset($exportBehavior->offset())
            ->setLimit($this->readBufferSize)
            ->addAssociation('categories')
            ->addAssociation('cover')
            ->addAssociation('manufacturer')
            ->addAssociation('media')
            ->addAssociation('prices')
            ->addAssociation('properties.group');

        $this->eventDispatcher->dispatch(
            new ProductExportProductCriteriaEvent($criteria, $productExport, $exportBehavior, $context)
        );

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

            $this->translator->resetInjection();
            $this->connection->delete('sales_channel_api_context', ['token' => $contextToken]);

            throw $exception;
        }

        $content = '';
        if ($exportBehavior->generateHeader()) {
            $content = $this->productExportRender->renderHeader($productExport, $context);
        }

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

                if ($productExport->isIncludeVariants() && !$product->getParentId() && $product->getChildCount() > 0) {
                    continue; // Skip main product if variants are included
                }
                if (!$productExport->isIncludeVariants() && $product->getParentId()) {
                    continue; // Skip variants unless they are included
                }

                $content .= $this->productExportRender->renderBody($productExport, $context, $data);
            }

            if ($exportBehavior->batchMode()) {
                break;
            }
        }

        if ($exportBehavior->generateFooter()) {
            $content .= $this->productExportRender->renderFooter($productExport, $context);
        }

        /** @var ProductExportChangeEncodingEvent $encodingEvent */
        $encodingEvent = $this->eventDispatcher->dispatch(
            new ProductExportChangeEncodingEvent($productExport, $content, mb_convert_encoding($content, $productExport->getEncoding()))
        );

        $this->translator->resetInjection();

        $this->connection->delete('sales_channel_api_context', ['token' => $contextToken]);

        if (empty($content)) {
            return null;
        }

        return new ProductExportResult(
            $encodingEvent->getEncodedContent(),
            $this->productExportValidator->validate($productExport, $encodingEvent->getEncodedContent()),
            $iterator->getTotal()
        );
    }
}
