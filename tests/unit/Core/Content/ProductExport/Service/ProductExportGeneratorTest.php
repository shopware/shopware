<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Content\ProductExport\Service\ProductExportGenerator;
use Shopware\Core\Content\ProductExport\Service\ProductExportRendererInterface;
use Shopware\Core\Content\ProductExport\Service\ProductExportValidatorInterface;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductStream\Exception\EmptyProductStreamException;
use Shopware\Core\Content\ProductStream\Exception\NoFilterException;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductExportGenerator::class)]
class ProductExportGeneratorTest extends TestCase
{
    private MockObject&ProductStreamBuilder $productStreamBuilder;

    /**
     * @var MockObject&SalesChannelRepository<SalesChannelProductCollection>
     */
    private MockObject&SalesChannelRepository $productRepository;

    private MockObject&ProductExportRendererInterface $productExportRender;

    private EventDispatcher $eventDispatcher;

    private MockObject&ProductExportValidatorInterface $productExportValidator;

    private MockObject&SalesChannelContextServiceInterface $salesChannelContextService;

    private MockObject&AbstractTranslator $translator;

    private MockObject&SalesChannelContextPersister $contextPersister;

    private MockObject&Connection $connection;

    private MockObject&SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler;

    private Stub&Environment $twig;

    private ProductDefinition $productDefinition;

    private MockObject&LanguageLocaleCodeProvider $languageLocaleProvider;

    private MockObject&TwigVariableParserFactory $parserFactory;

    private MockObject&CategoryBreadcrumbBuilder $breadcrumbBuilder;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class, ProductCategoryDefinition::class, ProductDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $productDefinition = $registry->get(ProductDefinition::class);
        static::assertInstanceOf(ProductDefinition::class, $productDefinition);

        $this->productStreamBuilder = $this->createMock(ProductStreamBuilder::class);
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->productRepository->method('getDefinition')->willReturn($productDefinition);
        $this->productExportRender = $this->createMock(ProductExportRendererInterface::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->productExportValidator = $this->createMock(ProductExportValidatorInterface::class);
        $this->salesChannelContextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $this->translator = $this->createMock(AbstractTranslator::class);
        $this->contextPersister = $this->createMock(SalesChannelContextPersister::class);
        $this->connection = $this->createMock(Connection::class);
        $this->seoUrlPlaceholderHandler = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $this->twig = static::createStub(Environment::class);
        $this->productDefinition = $productDefinition;
        $this->languageLocaleProvider = $this->createMock(LanguageLocaleCodeProvider::class);
        $this->parserFactory = $this->createMock(TwigVariableParserFactory::class);
        $this->breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
    }

    public function testGenerateWithInvalidProductExportId(): void
    {
        $productExport = $this->getProductExportEntity();

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get');
        $this->parserFactory->expects($this->once())->method('getParser');
        $this->productStreamBuilder->expects($this->once())->method('enrichCriteria');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId');
        $this->connection->expects($this->once())->method('delete');
        $this->productExportRender->expects($this->never())->method('renderBody');
        $this->productExportValidator->expects($this->never())->method('validate');
        $this->seoUrlPlaceholderHandler->expects($this->never())->method('replace');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'product',
                0,
                new SalesChannelProductCollection(),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));

        $generator = new ProductExportGenerator(
            $this->productStreamBuilder,
            $this->productRepository,
            $this->productExportRender,
            $this->eventDispatcher,
            $this->productExportValidator,
            $this->salesChannelContextService,
            $this->translator,
            $this->contextPersister,
            $this->connection,
            1,
            $this->seoUrlPlaceholderHandler,
            $this->twig,
            $this->productDefinition,
            $this->languageLocaleProvider,
            $this->parserFactory,
            $this->breadcrumbBuilder
        );

        $this->expectExceptionObject(ProductExportException::productExportNotFound($productExport->getId()));

        $generator->generate($productExport, new ExportBehavior());
    }

    public function testGenerateWithInvalidTemplate(): void
    {
        $productExport = $this->getProductExportEntity();

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get');
        $this->productStreamBuilder->expects($this->once())->method('enrichCriteria');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId');
        $this->productRepository->expects($this->never())->method('search');
        $this->connection->expects($this->never())->method('delete');
        $this->productExportRender->expects($this->never())->method('renderBody');
        $this->productExportValidator->expects($this->never())->method('validate');
        $this->seoUrlPlaceholderHandler->expects($this->never())->method('replace');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $errorMessage = 'error message';
        $twigVariableParser = static::createStub(TwigVariableParser::class);
        $twigVariableParser->method('parse')
            ->willThrowException(new \Exception($errorMessage));
        $this->parserFactory->expects($this->once())
            ->method('getParser')
            ->willReturn($twigVariableParser);

        $generator = new ProductExportGenerator(
            $this->productStreamBuilder,
            $this->productRepository,
            $this->productExportRender,
            $this->eventDispatcher,
            $this->productExportValidator,
            $this->salesChannelContextService,
            $this->translator,
            $this->contextPersister,
            $this->connection,
            1,
            $this->seoUrlPlaceholderHandler,
            $this->twig,
            $this->productDefinition,
            $this->languageLocaleProvider,
            $this->parserFactory,
            $this->breadcrumbBuilder
        );

        $this->expectExceptionObject(ProductExportException::renderProductException($errorMessage));

        $generator->generate($productExport, new ExportBehavior());
    }

    public function testGenerateFallsBackToBuildFiltersForInterfaceOnlyBuilder(): void
    {
        $productExport = $this->getProductExportEntity();

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get');
        $this->parserFactory->expects($this->once())->method('getParser');
        $this->productStreamBuilder->expects($this->never())->method('enrichCriteria');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId');
        $this->productRepository->expects($this->once())->method('search');
        $this->connection->expects($this->once())->method('delete');
        $this->productExportRender->expects($this->never())->method('renderBody');
        $this->productExportValidator->expects($this->never())->method('validate');
        $this->seoUrlPlaceholderHandler->expects($this->never())->method('replace');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        // A builder that only implements the deprecated interface (e.g. a decorator that has not yet adopted
        // AbstractProductStreamBuilder). The generator must fall back to buildFilters() without a TypeError.
        $productStreamBuilder = $this->createMock(ProductStreamBuilderInterface::class);
        $productStreamBuilder->expects($this->once())
            ->method('buildFilters')
            ->with('productStreamId', static::anything())
            ->willReturn([new EqualsFilter('product.product_stream', 'productStreamId')]);

        $generator = new ProductExportGenerator(
            $productStreamBuilder,
            $this->productRepository,
            $this->productExportRender,
            $this->eventDispatcher,
            $this->productExportValidator,
            $this->salesChannelContextService,
            $this->translator,
            $this->contextPersister,
            $this->connection,
            1,
            $this->seoUrlPlaceholderHandler,
            $this->twig,
            $this->productDefinition,
            $this->languageLocaleProvider,
            $this->parserFactory,
            $this->breadcrumbBuilder
        );

        // Reaching the "not found" result (no products resolve) proves the interface-only builder was routed
        // through buildFilters() without a TypeError.
        $this->expectExceptionObject(ProductExportException::productExportNotFound($productExport->getId()));

        $generator->generate($productExport, new ExportBehavior());
    }

    public function testGenerateNormalizesJsonlRows(): void
    {
        $productExport = $this->getProductExportEntity();
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_JSONL);
        $productExport->setBodyTemplate('{{ product.id }}{{ product.categories.count }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id');

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get')->willReturn($context);
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId')->with('languageId')->willReturn('en-GB');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->translator->expects($this->once())->method('resetInjection');
        $this->productStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->with(static::isInstanceOf(Criteria::class), 'productStreamId', $context->getContext());

        $twigVariableParser = $this->createMock(TwigVariableParser::class);
        $twigVariableParser->expects($this->once())->method('parse')->with('{{ product.id }}{{ product.categories.count }}')->willReturn(['product.categories.count']);
        $this->parserFactory->expects($this->once())->method('getParser')->willReturn($twigVariableParser);

        $results = [
            $this->createProductSearchResult($product, $context),
            $this->createEmptyProductSearchResult($context),
        ];
        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, SalesChannelContext $salesChannelContext) use ($context, &$results): EntitySearchResult {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $criteria->getTotalCountMode());
                static::assertSame($context, $salesChannelContext);
                static::assertTrue($criteria->hasAssociation('categories'));
                static::assertCount(1, $criteria->getAssociation('categories')->getFilters());
                static::assertEquals(new EqualsFilter('active', true), $criteria->getAssociation('categories')->getFilters()[0]);

                $next = array_shift($results);
                \assert($next instanceof EntitySearchResult);

                return $next;
            });

        $this->productExportRender->expects($this->once())
            ->method('renderBody')
            ->with($productExport, $context, static::callback(static function (array $data) use ($product): bool {
                return isset($data['product']) && $data['product'] === $product;
            }))
            ->willReturn('{"url":"https:\\/\\/example.com\\/product\\/1","title":"Product"}');
        $this->productExportRender->expects($this->never())->method('renderHeader');
        $this->productExportRender->expects($this->never())->method('renderFooter');

        $this->seoUrlPlaceholderHandler->expects($this->once())
            ->method('replace')
            ->with("{\"url\":\"https://example.com/product/1\",\"title\":\"Product\"}\n", '', $context)
            ->willReturnArgument(0);

        $this->productExportValidator->expects($this->once())
            ->method('validate')
            ->with($productExport, "{\"url\":\"https://example.com/product/1\",\"title\":\"Product\"}\n")
            ->willReturn([]);

        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $this->connection->expects($this->once())
            ->method('delete')
            ->with('sales_channel_api_context', static::arrayHasKey('token'));

        $generator = $this->createGenerator();
        $result = $generator->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNotNull($result);
        static::assertSame("{\"url\":\"https://example.com/product/1\",\"title\":\"Product\"}\n", $result->getContent());
        static::assertSame([], $result->getErrors());
    }

    public function testGenerateEncodesUnescapedSpacesInJsonlRowUrls(): void
    {
        $productExport = $this->getProductExportEntity();
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_JSONL);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id');

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get')->willReturn($context);
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId')->with('languageId')->willReturn('en-GB');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->translator->expects($this->once())->method('resetInjection');
        $this->productStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->with(static::isInstanceOf(Criteria::class), 'productStreamId', $context->getContext());

        $twigVariableParser = $this->createMock(TwigVariableParser::class);
        $twigVariableParser->expects($this->once())->method('parse')->with('{{ product.id }}')->willReturn([]);
        $this->parserFactory->expects($this->once())->method('getParser')->willReturn($twigVariableParser);

        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->createProductSearchResult($product, $context),
                $this->createEmptyProductSearchResult($context)
            );

        // Body contains an http URL with a literal space (e.g. media filename "Nice Burger.jpg")
        // and a non-URL string value with spaces that must remain untouched.
        $this->productExportRender->expects($this->once())
            ->method('renderBody')
            ->willReturn('{"image_url":"https:\/\/example.com\/media\/Nice Burger.jpg","title":"Nice Burger"}');
        $this->productExportRender->expects($this->never())->method('renderHeader');
        $this->productExportRender->expects($this->never())->method('renderFooter');

        $expectedNormalized = "{\"image_url\":\"https://example.com/media/Nice%20Burger.jpg\",\"title\":\"Nice Burger\"}\n";

        $this->seoUrlPlaceholderHandler->expects($this->once())
            ->method('replace')
            ->with($expectedNormalized, '', $context)
            ->willReturnArgument(0);

        $this->productExportValidator->expects($this->once())
            ->method('validate')
            ->with($productExport, $expectedNormalized)
            ->willReturn([]);

        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $this->connection->expects($this->once())
            ->method('delete')
            ->with('sales_channel_api_context', static::arrayHasKey('token'));

        $generator = $this->createGenerator();
        $result = $generator->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNotNull($result);
        static::assertSame($expectedNormalized, $result->getContent());
        static::assertSame([], $result->getErrors());
    }

    public function testGenerateThrowsExceptionForInvalidJsonlRow(): void
    {
        $productExport = $this->getProductExportEntity();
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_JSONL);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id');

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get')->willReturn($context);
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId')->with('languageId')->willReturn('en-GB');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->translator->expects($this->never())->method('resetInjection');
        $this->productStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->with(static::isInstanceOf(Criteria::class), 'productStreamId', $context->getContext());

        $twigVariableParser = $this->createMock(TwigVariableParser::class);
        $twigVariableParser->expects($this->once())->method('parse')->with('{{ product.id }}')->willReturn([]);
        $this->parserFactory->expects($this->once())->method('getParser')->willReturn($twigVariableParser);

        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createProductSearchResult($product, $context));

        $this->productExportRender->expects($this->once())
            ->method('renderBody')
            ->willReturn('{"url": }');

        $this->seoUrlPlaceholderHandler->expects($this->never())->method('replace');
        $this->productExportValidator->expects($this->never())->method('validate');
        $this->connection->expects($this->never())->method('delete');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $generator = $this->createGenerator();

        $this->expectExceptionObject(ProductExportException::renderProductException(
            'The JSONL row for product export "' . $productExport->getId() . '" could not be normalized: Syntax error'
        ));

        $generator->generate($productExport, new ExportBehavior(false, false, false, false, false));
    }

    public function testGenerateThrowsExceptionWhenSalesChannelDomainIsMissing(): void
    {
        $productExport = new ProductExportEntity();
        $productExport->setId('productExportId');

        $this->parserFactory->expects($this->once())
            ->method('getParser')
            ->willReturn(static::createStub(TwigVariableParser::class));

        $this->contextPersister->expects($this->never())->method('save');
        $this->salesChannelContextService->expects($this->never())->method('get');
        $this->productStreamBuilder->expects($this->never())->method('enrichCriteria');
        $this->translator->expects($this->never())->method('injectSettings');
        $this->languageLocaleProvider->expects($this->never())->method('getLocaleForLanguageId');
        $this->productRepository->expects($this->never())->method('search');
        $this->connection->expects($this->never())->method('delete');
        $this->productExportRender->expects($this->never())->method('renderBody');
        $this->productExportValidator->expects($this->never())->method('validate');
        $this->seoUrlPlaceholderHandler->expects($this->never())->method('replace');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $generator = $this->createGenerator();

        $this->expectExceptionObject(ProductExportException::salesChannelDomainNotFound('productExportId'));

        $generator->generate($productExport, new ExportBehavior());
    }

    public function testGenerateReturnsNullWhenNonJsonlBodyRendersEmptyContent(): void
    {
        $productExport = $this->getProductExportEntity();
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_CSV);
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id');

        $this->prepareGeneratorDependencies($context, '{{ product.id }}');
        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->createProductSearchResult($product, $context),
                $this->createEmptyProductSearchResult($context)
            );
        $this->productExportRender->expects($this->once())
            ->method('renderBody')
            ->willReturn('   ');
        $this->seoUrlPlaceholderHandler->expects($this->once())
            ->method('replace')
            ->with('', '', $context)
            ->willReturn('');
        $this->productExportValidator->expects($this->never())->method('validate');
        $this->connection->expects($this->once())->method('delete');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $generator = $this->createGenerator();

        static::assertNull($generator->generate($productExport, new ExportBehavior(false, false, false, false, false)));
    }

    public function testGenerateUsesUnfilteredProductsWhenProductStreamHasNoFilters(): void
    {
        $productExport = $this->getProductExportEntity();
        $productStreamId = Uuid::randomHex();
        $productExport->setProductStreamId($productStreamId);
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_CSV);
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id');

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get')->willReturn($context);
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId')->with('languageId')->willReturn('en-GB');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->translator->expects($this->once())->method('resetInjection');
        $this->productStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->with(static::isInstanceOf(Criteria::class), $productStreamId, $context->getContext())
            ->willThrowException(new EmptyProductStreamException($productStreamId));

        $twigVariableParser = $this->createMock(TwigVariableParser::class);
        $twigVariableParser->expects($this->once())->method('parse')->with('{{ product.id }}')->willReturn([]);
        $this->parserFactory->expects($this->once())->method('getParser')->willReturn($twigVariableParser);

        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->createProductSearchResult($product, $context),
                $this->createEmptyProductSearchResult($context)
            );
        $this->productExportRender->expects($this->once())->method('renderBody')->willReturn('product');
        $this->seoUrlPlaceholderHandler->expects($this->once())->method('replace')->with('product', '', $context)->willReturnArgument(0);
        $this->productExportValidator->expects($this->once())->method('validate')->with($productExport, 'product')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $result = $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNotNull($result);
        static::assertSame('product', $result->getContent());
    }

    public function testGenerateRethrowsNoFilterExceptionWhenProductStreamIsInvalid(): void
    {
        $productExport = $this->getProductExportEntity();
        $productStreamId = Uuid::randomHex();
        $productExport->setProductStreamId($productStreamId);

        $context = $this->createSalesChannelContext();

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get')->willReturn($context);
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId')->with('languageId')->willReturn('en-GB');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->productStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->with(static::isInstanceOf(Criteria::class), $productStreamId, $context->getContext())
            ->willThrowException(new NoFilterException($productStreamId));
        $this->connection->expects($this->never())->method('delete');
        $this->productRepository->expects($this->never())->method('search');
        $this->productExportRender->expects($this->never())->method('renderBody');
        $this->productExportValidator->expects($this->never())->method('validate');
        $this->seoUrlPlaceholderHandler->expects($this->never())->method('replace');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $this->parserFactory->expects($this->once())->method('getParser')->willReturn(static::createStub(TwigVariableParser::class));

        static::expectException(NoFilterException::class);

        $this->createGenerator()->generate($productExport, new ExportBehavior());
    }

    public function testGenerateSkipsVariantsWhenIncludeVariantsIsDisabled(): void
    {
        $productExport = $this->getProductExportEntity();
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_CSV);
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id');

        $this->prepareGeneratorDependencies($context, '{{ product.id }}');

        $results = [
            $this->createProductSearchResult($product, $context),
            $this->createEmptyProductSearchResult($context),
        ];
        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$results): EntitySearchResult {
                $parentIdFilters = array_filter($criteria->getFilters(), static fn ($f) => $f instanceof EqualsFilter && $f->getField() === 'parentId' && $f->getValue() === null);
                static::assertNotEmpty($parentIdFilters, 'Criteria must contain a parentId = null filter when variants are excluded');

                $next = array_shift($results);
                \assert($next instanceof EntitySearchResult);

                return $next;
            });

        $this->productExportRender->expects($this->once())->method('renderBody')->willReturn('product');
        $this->seoUrlPlaceholderHandler->expects($this->once())->method('replace')->willReturnArgument(0);
        $this->productExportValidator->expects($this->once())->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $result = $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNotNull($result);
    }

    public function testGenerateSkipsParentProductsWhenVariantsAreIncluded(): void
    {
        $productExport = $this->getProductExportEntity();
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_CSV);
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(true);

        $context = $this->createSalesChannelContext();
        $variant = $this->createProduct('variant-id', 'parent-id');

        $this->prepareGeneratorDependencies($context, '{{ product.id }}');

        $results = [
            $this->createProductSearchResult($variant, $context),
            $this->createEmptyProductSearchResult($context),
        ];
        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$results): EntitySearchResult {
                $orFilters = array_filter($criteria->getFilters(), static fn ($f) => $f instanceof OrFilter);
                static::assertNotEmpty($orFilters, 'Criteria must contain an OrFilter to exclude parent products when variants are included');

                $next = array_shift($results);
                \assert($next instanceof EntitySearchResult);

                return $next;
            });

        $this->productExportRender->expects($this->once())->method('renderBody')->willReturn('variant');
        $this->seoUrlPlaceholderHandler->expects($this->once())->method('replace')->willReturnArgument(0);
        $this->productExportValidator->expects($this->once())->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $result = $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNotNull($result);
    }

    public function testGenerateJsonlSkipsParentProductsAndAddsLineSeparators(): void
    {
        $productExport = $this->getProductExportEntity();
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_JSONL);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(true);

        $context = $this->createSalesChannelContext();
        $parent = $this->createProduct('parent-id', null, 1, 1);
        $variantA = $this->createProduct('variant-a', 'parent-id', 0, 2);
        $variantB = $this->createProduct('variant-b', 'parent-id', 0, 3);

        $this->prepareGeneratorDependencies($context, '{{ product.id }}');
        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->createProductSearchResultCollection([$parent, $variantA, $variantB], $context),
                $this->createEmptyProductSearchResult($context)
            );
        $this->productExportRender->expects($this->exactly(2))
            ->method('renderBody')
            ->willReturnOnConsecutiveCalls(
                '{"id":"variant-a","url":"https:\/\/example.com\/a"}',
                '{"id":"variant-b","url":"https:\/\/example.com\/b"}'
            );
        $this->seoUrlPlaceholderHandler->expects($this->once())
            ->method('replace')
            ->with("{\"id\":\"variant-a\",\"url\":\"https://example.com/a\"}\n{\"id\":\"variant-b\",\"url\":\"https://example.com/b\"}\n", '', $context)
            ->willReturnArgument(0);
        $this->productExportValidator->expects($this->once())
            ->method('validate')
            ->with($productExport, "{\"id\":\"variant-a\",\"url\":\"https://example.com/a\"}\n{\"id\":\"variant-b\",\"url\":\"https://example.com/b\"}\n")
            ->willReturn([]);
        $this->connection->expects($this->once())->method('delete');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $result = $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNotNull($result);
        static::assertSame("{\"id\":\"variant-a\",\"url\":\"https://example.com/a\"}\n{\"id\":\"variant-b\",\"url\":\"https://example.com/b\"}\n", $result->getContent());
    }

    public function testGenerateJsonlReturnsEmptyContentWhenRowsAreSkippedOrBlankInBatchMode(): void
    {
        $productExport = $this->getProductExportEntity();
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_JSONL);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $variant = $this->createProduct('variant-id', 'parent-id', 0, 1);
        $simple = $this->createProduct('simple-id', null, 0, 2);

        $this->prepareGeneratorDependencies($context, '{{ product.id }}');
        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createProductSearchResultCollection([$variant, $simple], $context));
        $this->productExportRender->expects($this->once())
            ->method('renderBody')
            ->with($productExport, $context, static::callback(static fn (array $data): bool => $data['product'] === $simple))
            ->willReturn(" \n\t ");
        $this->seoUrlPlaceholderHandler->expects($this->once())->method('replace')->with('', '', $context)->willReturn('');
        $this->productExportValidator->expects($this->once())->method('validate')->with($productExport, '')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $result = $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, true, false, false));

        static::assertNotNull($result);
        static::assertSame('', $result->getContent());
    }

    public function testGenerateBatchModeSignalsNextBatchWithKeysetCursor(): void
    {
        $productExport = $this->getProductExportEntity();
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_CSV);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id', null, 0, 42);

        $this->prepareGeneratorDependencies($context, '{{ product.id }}');

        // A full buffer (1 product, readBufferSize = 1) => another batch must follow,
        // and the cursor is the highest autoIncrement seen. Only one search happens
        // because batch mode hands off to the next message instead of looping.
        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createProductSearchResult($product, $context));

        $this->productExportRender->expects($this->once())->method('renderBody')->willReturn('product');
        $this->seoUrlPlaceholderHandler->expects($this->once())->method('replace')->willReturnArgument(0);
        $this->productExportValidator->expects($this->once())->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $result = $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, true, false, false));

        static::assertNotNull($result);
        static::assertTrue($result->hasNextBatch());
        static::assertSame(42, $result->getOffset());
    }

    public function testGenerateBatchModeStopsWhenBufferIsNotFilled(): void
    {
        $productExport = $this->getProductExportEntity();
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_CSV);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();

        $this->prepareGeneratorDependencies($context, '{{ product.id }}');

        // Resuming past cursor 41; the batch comes back empty (buffer not filled),
        // so this is the final batch and no further message is requested. An empty
        // page beyond the first cursor must not raise productExportNotFound.
        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createEmptyProductSearchResult($context));

        $this->productExportRender->expects($this->never())->method('renderBody');
        $this->seoUrlPlaceholderHandler->expects($this->once())->method('replace')->willReturnArgument(0);
        $this->productExportValidator->expects($this->once())->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');
        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $result = $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, true, false, false, 41));

        static::assertNotNull($result);
        static::assertFalse($result->hasNextBatch());
        static::assertSame(41, $result->getOffset());
    }

    public function testGeneratePopulatesSeoCategoryForExportedProducts(): void
    {
        // Populates seoCategory so feed templates can render the configured main category.
        $productExport = $this->getProductExportEntity();
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_CSV);
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id');
        $product->setMainCategories($this->mainCategoryCollectionForSalesChannel('storefrontSalesChannelId'));

        $this->prepareGeneratorDependencies($context, '{{ product.id }}');
        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->createProductSearchResult($product, $context),
                $this->createEmptyProductSearchResult($context)
            );

        $mainCategory = new CategoryEntity();
        $mainCategory->setId('main-category-id');
        $mainCategory->setUniqueIdentifier('main-category-id');
        $this->breadcrumbBuilder->expects($this->once())
            ->method('getProductSeoCategory')
            ->with($product, $context)
            ->willReturn($mainCategory);

        $this->productExportRender->expects($this->once())->method('renderBody')->willReturn('rendered');
        $this->seoUrlPlaceholderHandler->expects($this->once())->method('replace')->willReturnArgument(0);
        $this->productExportValidator->expects($this->once())->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');

        $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertSame($mainCategory, $product->getSeoCategory());
    }

    public function testGeneratePopulatesSeoCategoryForJsonlPath(): void
    {
        // The JSONL path must also populate seoCategory.
        $productExport = $this->getProductExportEntity();
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_JSONL);
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setBodyTemplate('{"id":"{{ product.id }}"}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id');
        $product->setMainCategories($this->mainCategoryCollectionForSalesChannel('storefrontSalesChannelId'));

        $this->prepareGeneratorDependencies($context, '{"id":"{{ product.id }}"}');
        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->createProductSearchResult($product, $context),
                $this->createEmptyProductSearchResult($context)
            );

        $mainCategory = new CategoryEntity();
        $mainCategory->setId('main-category-id');
        $mainCategory->setUniqueIdentifier('main-category-id');
        $this->breadcrumbBuilder->expects($this->once())
            ->method('getProductSeoCategory')
            ->with($product, $context)
            ->willReturn($mainCategory);

        $this->productExportRender->expects($this->once())->method('renderBody')->willReturn('{"id":"product-id"}');
        $this->seoUrlPlaceholderHandler->expects($this->once())->method('replace')->willReturnArgument(0);
        $this->productExportValidator->expects($this->once())->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');

        $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertSame($mainCategory, $product->getSeoCategory());
    }

    public function testGenerateSkipsSeoCategoryResolutionWhenProductHasNoMainCategory(): void
    {
        // Without a main category the builder is not called; the template uses categories.first.
        $productExport = $this->getProductExportEntity();
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_CSV);
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id');

        $this->prepareGeneratorDependencies($context, '{{ product.id }}');
        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->createProductSearchResult($product, $context),
                $this->createEmptyProductSearchResult($context)
            );

        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $this->productExportRender->expects($this->once())->method('renderBody')->willReturn('rendered');
        $this->seoUrlPlaceholderHandler->expects($this->once())->method('replace')->willReturnArgument(0);
        $this->productExportValidator->expects($this->once())->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');

        $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNull($product->getSeoCategory());
    }

    private function createGenerator(): ProductExportGenerator
    {
        return new ProductExportGenerator(
            $this->productStreamBuilder,
            $this->productRepository,
            $this->productExportRender,
            $this->eventDispatcher,
            $this->productExportValidator,
            $this->salesChannelContextService,
            $this->translator,
            $this->contextPersister,
            $this->connection,
            1,
            $this->seoUrlPlaceholderHandler,
            $this->twig,
            $this->productDefinition,
            $this->languageLocaleProvider,
            $this->parserFactory,
            $this->breadcrumbBuilder
        );
    }

    private function prepareGeneratorDependencies(SalesChannelContext $context, string $bodyTemplate): void
    {
        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get')->willReturn($context);
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId')->with('languageId')->willReturn('en-GB');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->translator->expects($this->once())->method('resetInjection');
        $this->productStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->with(static::isInstanceOf(Criteria::class), 'productStreamId', $context->getContext());

        $twigVariableParser = $this->createMock(TwigVariableParser::class);
        $twigVariableParser->expects($this->once())->method('parse')->with($bodyTemplate)->willReturn([]);
        $this->parserFactory->expects($this->once())->method('getParser')->willReturn($twigVariableParser);
    }

    private function getProductExportEntity(): ProductExportEntity
    {
        $productExport = new ProductExportEntity();
        $productExport->setId('productExportId');
        $productExport->setCurrencyId('currencyId');
        $productExport->setSalesChannelId('salesChannelId');
        $productExport->setStorefrontSalesChannelId('storefrontSalesChannelId');
        $productExport->setProductStreamId('productStreamId');
        $productExport->setIncludeVariants(false);

        $salesChannelDomain = new SalesChannelDomainEntity();
        $salesChannelDomain->setLanguageId('languageId');
        $salesChannelDomain->setUrl('');
        $productExport->setSalesChannelDomain($salesChannelDomain);

        return $productExport;
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('storefrontSalesChannelId');

        return Generator::generateSalesChannelContext(
            baseContext: Context::createDefaultContext(),
            salesChannel: $salesChannel
        );
    }

    private function createProduct(string $id, ?string $parentId = null, int $childCount = 0, int $autoIncrement = 1): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($id);
        $product->setParentId($parentId);
        $product->setChildCount($childCount);
        $product->setAutoIncrement($autoIncrement);
        $product->internalSetEntityData('product', new FieldVisibility([]));

        return $product;
    }

    private function mainCategoryCollectionForSalesChannel(string $salesChannelId): MainCategoryCollection
    {
        $assignment = new MainCategoryEntity();
        $assignment->setId('main-category-' . $salesChannelId);
        $assignment->setUniqueIdentifier('main-category-' . $salesChannelId);
        $assignment->setSalesChannelId($salesChannelId);

        return new MainCategoryCollection([$assignment]);
    }

    /**
     * @return EntitySearchResult<SalesChannelProductCollection>
     */
    private function createProductSearchResult(
        SalesChannelProductEntity $product,
        SalesChannelContext $context
    ): EntitySearchResult {
        $criteria = new Criteria([$product->getId()]);

        return new EntitySearchResult(
            'product',
            1,
            new SalesChannelProductCollection([$product]),
            null,
            $criteria,
            $context->getContext()
        );
    }

    /**
     * @param list<SalesChannelProductEntity> $products
     *
     * @return EntitySearchResult<SalesChannelProductCollection>
     */
    private function createProductSearchResultCollection(array $products, SalesChannelContext $context): EntitySearchResult
    {
        $criteria = new Criteria(array_map(static fn (SalesChannelProductEntity $product): string => $product->getId(), $products));

        return new EntitySearchResult(
            'product',
            \count($products),
            new SalesChannelProductCollection($products),
            null,
            $criteria,
            $context->getContext()
        );
    }

    /**
     * @return EntitySearchResult<SalesChannelProductCollection>
     */
    private function createEmptyProductSearchResult(SalesChannelContext $context): EntitySearchResult
    {
        return new EntitySearchResult(
            'product',
            0,
            new SalesChannelProductCollection(),
            null,
            new Criteria(),
            $context->getContext()
        );
    }
}
