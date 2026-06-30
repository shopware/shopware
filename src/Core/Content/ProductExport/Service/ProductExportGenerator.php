<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Doctrine\DBAL\Connection;
use Monolog\Level;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\ProductExport\Event\ProductExportChangeEncodingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportProductCriteriaEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductExport\Struct\ProductExportResult;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

#[Package('inventory')]
class ProductExportGenerator implements ProductExportGeneratorInterface
{
    private readonly TwigVariableParser $twigVariableParser;

    /**
     * @internal
     *
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(
        private readonly ProductStreamBuilderInterface $productStreamBuilder,
        private readonly SalesChannelRepository $productRepository,
        private readonly ProductExportRendererInterface $productExportRender,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProductExportValidatorInterface $productExportValidator,
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        private readonly AbstractTranslator $translator,
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly Connection $connection,
        private readonly int $readBufferSize,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        Environment $twig,
        private readonly ProductDefinition $productDefinition,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
        TwigVariableParserFactory $parserFactory
    ) {
        $this->twigVariableParser = $parserFactory->getParser($twig);
    }

    public function generate(ProductExportEntity $productExport, ExportBehavior $exportBehavior): ?ProductExportResult
    {
        $domain = $productExport->getSalesChannelDomain();

        if ($domain === null) {
            throw ProductExportException::salesChannelDomainNotFound($productExport->getId());
        }

        $contextToken = Uuid::randomHex();
        $this->contextPersister->save(
            $contextToken,
            [
                SalesChannelContextService::CURRENCY_ID => $productExport->getCurrencyId(),
            ],
            $productExport->getSalesChannelId()
        );

        $languageId = $domain->getLanguageId();

        $context = $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters(
                $productExport->getStorefrontSalesChannelId(),
                $contextToken,
                $languageId,
                $productExport->getCurrencyId()
            )
        );

        $this->translator->injectSettings(
            $productExport->getStorefrontSalesChannelId(),
            $languageId,
            $this->languageLocaleProvider->getLocaleForLanguageId($languageId),
            $context->getContext()
        );

        $filters = $this->productStreamBuilder->buildFilters(
            $productExport->getProductStreamId(),
            $context->getContext()
        );

        $associations = $this->getAssociations($productExport, $context);

        $criteria = new Criteria();
        $criteria
            ->setTitle('product-export::products')
            ->addFilter(...$filters)
            ->addSorting(new FieldSorting('autoIncrement', FieldSorting::ASCENDING))
            ->setLimit($this->readBufferSize);

        if ($productExport->isIncludeVariants()) {
            // Only fetch variants and standalone products; parent products that have variants are skipped
            $criteria->addFilter(new OrFilter([
                new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('parentId', null)]),
                new EqualsFilter('childCount', 0),
            ]));
        } else {
            // Only fetch main and standalone products
            $criteria->addFilter(new EqualsFilter('parentId', null));
        }

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        if ($criteria->hasAssociation('categories')) {
            $criteria->getAssociation('categories')
                ->addFilter(new EqualsFilter('active', true));
        }

        $this->eventDispatcher->dispatch(
            new ProductExportProductCriteriaEvent($criteria, $productExport, $exportBehavior, $context)
        );

        // Keyset pagination over product.autoIncrement: each batch seeks past the last
        // exported id instead of using OFFSET, so the cost stays constant regardless of
        // how deep into the catalog we are. The last batch is the one that does not fill
        // the read buffer, so no COUNT query is needed to drive pagination.
        $cursor = $exportBehavior->lastId();
        $products = $this->fetchBatch($criteria, $cursor, $context);

        if ($products->count() === 0 && $cursor === 0) {
            $exception = ProductExportException::productExportNotFound($productExport->getId());

            $loggingEvent = new ProductExportLoggingEvent(
                $context->getContext(),
                $exception->getMessage(),
                Level::Warning,
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

        $isJsonl = $productExport->getFileFormat() === ProductExportEntity::FILE_FORMAT_JSONL;
        $body = '';
        $fetched = 0;

        while ($products->count() > 0) {
            $fetched = $products->count();

            foreach ($products as $product) {
                $cursor = $product->getAutoIncrement();

                if ($productExport->isIncludeVariants() && !$product->getParentId() && $product->getChildCount() > 0) {
                    continue; // Skip main product if variants are included
                }
                if (!$productExport->isIncludeVariants() && $product->getParentId()) {
                    continue; // Skip variants unless they are included
                }

                $data = $productContext->getContext();
                $data['product'] = $product;

                $renderedBody = $this->renderProductBody($productExport, $context, $data);
                if ($renderedBody === null) {
                    continue;
                }

                if ($isJsonl) {
                    $row = $this->normalizeJsonlRow($productExport, $renderedBody);
                    $body .= $body === '' ? $row : \PHP_EOL . $row;
                } else {
                    $body .= $renderedBody;
                }
            }

            // In batch mode the partial-generation handler dispatches the next message;
            // a short buffer means there is no further batch.
            if ($exportBehavior->batchMode() || $fetched < $this->readBufferSize) {
                break;
            }

            $products = $this->fetchBatch($criteria, $cursor, $context);
        }

        if ($isJsonl && $body !== '') {
            $body .= \PHP_EOL;
        }

        $content .= $body;

        if ($exportBehavior->generateFooter()) {
            $content .= $this->productExportRender->renderFooter($productExport, $context);
        }

        $content = $this->seoUrlPlaceholderHandler->replace($content, $domain->getUrl(), $context);

        $encodedContent = mb_convert_encoding($content, $productExport->getEncoding());
        \assert(\is_string($encodedContent));
        $encodingEvent = $this->eventDispatcher->dispatch(
            new ProductExportChangeEncodingEvent($productExport, $content, $encodedContent)
        );

        $this->translator->resetInjection();

        $this->connection->delete('sales_channel_api_context', ['token' => $contextToken]);

        if ($content === '' && !$exportBehavior->batchMode()) {
            return null;
        }

        return new ProductExportResult(
            $encodingEvent->getEncodedContent(),
            $this->productExportValidator->validate($productExport, $encodingEvent->getEncodedContent()),
            $fetched,
            $cursor,
            $exportBehavior->batchMode() && $fetched === $this->readBufferSize
        );
    }

    private function fetchBatch(Criteria $criteria, int $cursor, SalesChannelContext $context): SalesChannelProductCollection
    {
        $batchCriteria = clone $criteria;
        $batchCriteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        if ($cursor > 0) {
            $batchCriteria->addFilter(new RangeFilter('autoIncrement', [RangeFilter::GT => $cursor]));
        }

        /** @var SalesChannelProductCollection $products */
        $products = $this->productRepository->search($batchCriteria, $context)->getEntities();

        return $products;
    }

    private function normalizeJsonlRow(ProductExportEntity $productExport, string $renderedBody): string
    {
        try {
            $decoded = json_decode($renderedBody, true, 512, \JSON_THROW_ON_ERROR);

            // URLs from media filenames may contain unescaped spaces; encode them so
            // the row passes downstream RFC 3986 validation (FILTER_VALIDATE_URL).
            array_walk_recursive($decoded, static function (mixed &$value): void {
                if (\is_string($value) && preg_match('#^https?://#i', $value)) {
                    $value = str_replace(' ', '%20', $value);
                }
            });

            return (string) json_encode($decoded, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            throw ProductExportException::renderProductException(
                'The JSONL row for product export "' . $productExport->getId() . '" could not be normalized: ' . $exception->getMessage()
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderProductBody(
        ProductExportEntity $productExport,
        SalesChannelContext $context,
        array $data
    ): ?string {
        $renderedBody = $this->productExportRender->renderBody($productExport, $context, $data);

        if (trim($renderedBody) === '') {
            return null;
        }

        return $renderedBody;
    }

    /**
     * @return array<string>
     */
    private function getAssociations(ProductExportEntity $productExport, SalesChannelContext $context): array
    {
        try {
            $variables = $this->twigVariableParser->parse((string) $productExport->getBodyTemplate());
        } catch (\Exception $e) {
            $e = ProductExportException::renderProductException($e->getMessage());

            $loggingEvent = new ProductExportLoggingEvent($context->getContext(), $e->getMessage(), Level::Warning, $e);

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
