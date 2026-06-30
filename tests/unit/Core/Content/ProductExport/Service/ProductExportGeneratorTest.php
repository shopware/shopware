<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
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
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Context;
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
    private MockObject&ProductStreamBuilderInterface $productStreamBuilder;

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

    private MockObject&Environment $twig;

    private ProductDefinition $productDefinition;

    private MockObject&LanguageLocaleCodeProvider $languageLocaleProvider;

    private MockObject&TwigVariableParserFactory $parserFactory;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class, ProductCategoryDefinition::class, ProductDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
        $productDefinition = $registry->get(ProductDefinition::class);
        static::assertInstanceOf(ProductDefinition::class, $productDefinition);

        $this->productStreamBuilder = $this->createMock(ProductStreamBuilderInterface::class);
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->productExportRender = $this->createMock(ProductExportRendererInterface::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->productExportValidator = $this->createMock(ProductExportValidatorInterface::class);
        $this->salesChannelContextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $this->translator = $this->createMock(AbstractTranslator::class);
        $this->contextPersister = $this->createMock(SalesChannelContextPersister::class);
        $this->connection = $this->createMock(Connection::class);
        $this->seoUrlPlaceholderHandler = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->productDefinition = $productDefinition;
        $this->languageLocaleProvider = $this->createMock(LanguageLocaleCodeProvider::class);
        $this->parserFactory = $this->createMock(TwigVariableParserFactory::class);
    }

    public function testGenerateWithInvalidProductExportId(): void
    {
        $productExport = $this->getProductExportEntity();

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get');
        $this->parserFactory->expects($this->once())->method('getParser');

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
            $this->parserFactory
        );

        $this->expectExceptionObject(ProductExportException::productExportNotFound($productExport->getId()));

        $generator->generate($productExport, new ExportBehavior());
    }

    public function testGenerateWithInvalidTemplate(): void
    {
        $productExport = $this->getProductExportEntity();

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get');

        $errorMessage = 'error message';
        $twigVariableParser = $this->createMock(TwigVariableParser::class);
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
            $this->parserFactory
        );

        $this->expectExceptionObject(ProductExportException::renderProductException($errorMessage));

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
        $this->productStreamBuilder->expects($this->once())->method('buildFilters')->with('productStreamId', $context->getContext())->willReturn([]);

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

                return array_shift($results);
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

        $this->productExportValidator = $this->createMock(ProductExportValidatorInterface::class);
        $this->productExportValidator->expects($this->once())
            ->method('validate')
            ->with($productExport, "{\"url\":\"https://example.com/product/1\",\"title\":\"Product\"}\n")
            ->willReturn([]);

        $this->connection->expects($this->once())
            ->method('delete')
            ->with('sales_channel_api_context', static::arrayHasKey('token'));

        $generator = $this->createGenerator();
        $result = $generator->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNotNull($result);
        static::assertSame("{\"url\":\"https://example.com/product/1\",\"title\":\"Product\"}\n", $result->getContent());
        static::assertSame(1, $result->getTotal());
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
        $this->productStreamBuilder->expects($this->once())->method('buildFilters')->with('productStreamId', $context->getContext())->willReturn([]);

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

        $this->productExportValidator = $this->createMock(ProductExportValidatorInterface::class);
        $this->productExportValidator->expects($this->once())
            ->method('validate')
            ->with($productExport, $expectedNormalized)
            ->willReturn([]);

        $this->connection->expects($this->once())
            ->method('delete')
            ->with('sales_channel_api_context', static::arrayHasKey('token'));

        $generator = $this->createGenerator();
        $result = $generator->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNotNull($result);
        static::assertSame($expectedNormalized, $result->getContent());
        static::assertSame(1, $result->getTotal());
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
        $this->productStreamBuilder->expects($this->once())->method('buildFilters')->with('productStreamId', $context->getContext())->willReturn([]);

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
            ->willReturn($this->createMock(TwigVariableParser::class));

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

        $generator = $this->createGenerator();

        static::assertNull($generator->generate($productExport, new ExportBehavior(false, false, false, false, false)));
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

                return array_shift($results);
            });

        $this->productExportRender->method('renderBody')->willReturn('product');
        $this->seoUrlPlaceholderHandler->method('replace')->willReturnArgument(0);
        $this->productExportValidator->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');

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

                return array_shift($results);
            });

        $this->productExportRender->method('renderBody')->willReturn('variant');
        $this->seoUrlPlaceholderHandler->method('replace')->willReturnArgument(0);
        $this->productExportValidator->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');

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

        $this->productExportRender->method('renderBody')->willReturn('product');
        $this->seoUrlPlaceholderHandler->method('replace')->willReturnArgument(0);
        $this->productExportValidator->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');

        $result = $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, true, false, false));

        static::assertNotNull($result);
        static::assertTrue($result->hasNextBatch());
        static::assertSame(42, $result->getLastId());
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
        $this->seoUrlPlaceholderHandler->method('replace')->willReturnArgument(0);
        $this->productExportValidator->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');

        $result = $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, true, false, false, 41));

        static::assertNotNull($result);
        static::assertFalse($result->hasNextBatch());
        static::assertSame(41, $result->getLastId());
    }

    public function testGenerateUsesStreamMappingWhenIndexingEnabledAndStreamIsMapped(): void
    {
        $streamId = Uuid::randomHex();

        $productExport = $this->getProductExportEntity();
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_CSV);
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);
        $productExport->setProductStreamId($streamId);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id', null, 0, 7);

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get')->willReturn($context);
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId')->with('languageId')->willReturn('en-GB');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->translator->expects($this->once())->method('resetInjection');

        $twigVariableParser = $this->createMock(TwigVariableParser::class);
        $twigVariableParser->expects($this->once())->method('parse')->with('{{ product.id }}')->willReturn([]);
        $this->parserFactory->expects($this->once())->method('getParser')->willReturn($twigVariableParser);

        // Indexing is on and the stream mapping has rows -> the dynamic filter builder must
        // not be used; the export filters by the precomputed streamIds mapping instead.
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('1');
        $this->productStreamBuilder->expects($this->never())->method('buildFilters');

        $results = [
            $this->createProductSearchResult($product, $context),
            $this->createEmptyProductSearchResult($context),
        ];
        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$results, $streamId): EntitySearchResult {
                $streamFilters = array_filter(
                    $criteria->getFilters(),
                    static fn ($f) => $f instanceof EqualsFilter && $f->getField() === 'streamIds' && $f->getValue() === $streamId
                );
                static::assertNotEmpty($streamFilters, 'Criteria must filter by streamIds when the product stream mapping is used');

                return array_shift($results);
            });

        $this->productExportRender->method('renderBody')->willReturn('product');
        $this->seoUrlPlaceholderHandler->method('replace')->willReturnArgument(0);
        $this->productExportValidator->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');

        $result = $this->createGenerator(true)->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNotNull($result);
    }

    public function testGenerateFallsBackToDynamicFiltersWhenStreamNotYetMapped(): void
    {
        $streamId = Uuid::randomHex();

        $productExport = $this->getProductExportEntity();
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_CSV);
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);
        $productExport->setProductStreamId($streamId);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id', null, 0, 7);

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get')->willReturn($context);
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId')->with('languageId')->willReturn('en-GB');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->translator->expects($this->once())->method('resetInjection');

        $twigVariableParser = $this->createMock(TwigVariableParser::class);
        $twigVariableParser->expects($this->once())->method('parse')->with('{{ product.id }}')->willReturn([]);
        $this->parserFactory->expects($this->once())->method('getParser')->willReturn($twigVariableParser);

        // Indexing is on but the mapping has no rows for this stream yet -> fall back to the
        // dynamic filter builder so a freshly created stream still exports immediately.
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);
        $this->productStreamBuilder->expects($this->once())
            ->method('buildFilters')
            ->with($streamId, $context->getContext())
            ->willReturn([]);

        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->createProductSearchResult($product, $context),
                $this->createEmptyProductSearchResult($context)
            );

        $this->productExportRender->method('renderBody')->willReturn('product');
        $this->seoUrlPlaceholderHandler->method('replace')->willReturnArgument(0);
        $this->productExportValidator->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');

        $result = $this->createGenerator(true)->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNotNull($result);
    }

    private function createGenerator(bool $productStreamIndexingEnabled = false): ProductExportGenerator
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
            $productStreamIndexingEnabled
        );
    }

    private function prepareGeneratorDependencies(SalesChannelContext $context, string $bodyTemplate): void
    {
        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get')->willReturn($context);
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId')->with('languageId')->willReturn('en-GB');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->translator->expects($this->once())->method('resetInjection');
        $this->productStreamBuilder->expects($this->once())->method('buildFilters')->with('productStreamId', $context->getContext())->willReturn([]);

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

        return $product;
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
