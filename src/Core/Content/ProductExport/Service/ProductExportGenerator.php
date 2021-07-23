<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductExport\Event\ProductExportChangeEncodingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportProductCriteriaEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopware\Core\Content\ProductExport\Exception\EmptyExportException;
use Shopware\Core\Content\ProductExport\Exception\RenderProductException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductExport\Struct\ProductExportResult;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\SalesChannelRepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

    private SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler;

    private TwigVariableParser $twigVariableParser;

    private ProductDefinition $productDefinition;

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
        int $readBufferSize,
        SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        TwigVariableParser $twigVariableParser,
        ProductDefinition $productDefinition
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
        $this->seoUrlPlaceholderHandler = $seoUrlPlaceholderHandler;
        $this->twigVariableParser = $twigVariableParser;
        $this->productDefinition = $productDefinition;
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

        $associations = $this->getAssociations($productExport, $context);

        $criteria = new Criteria();
        $criteria->setTitle('product-export::products');

        $criteria
            ->addFilter(...$filters)
            ->setOffset($exportBehavior->offset())
            ->setLimit($this->readBufferSize);

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

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

        $body = '';
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

                $body .= $this->productExportRender->renderBody($productExport, $context, $data);
            }

            if ($exportBehavior->batchMode()) {
                break;
            }
        }
        $content .= $this->seoUrlPlaceholderHandler->replace($body, $productExport->getSalesChannelDomain()->getUrl(), $context);

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

    private function getAssociations(ProductExportEntity $productExport, SalesChannelContext $context): array
    {
        try {
            $variables = $this->twigVariableParser->parse((string) $productExport->getBodyTemplate());
        } catch (\Exception $e) {
            $e = new RenderProductException($e->getMessage());

            $loggingEvent = new ProductExportLoggingEvent($context->getContext(), $e->getMessage(), Logger::ERROR, $e);

            $this->eventDispatcher->dispatch($loggingEvent);

            throw $e;
        }

        $associations = [];
        foreach ($variables as $variable) {
            $associations[] = EntityDefinitionQueryHelper::getAssociationPath($variable, $this->productDefinition);
        }

        return array_filter(array_unique($associations));
    }
}
