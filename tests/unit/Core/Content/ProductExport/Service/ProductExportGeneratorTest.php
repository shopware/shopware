<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
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
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductExportGenerator::class)]
class ProductExportGeneratorTest extends TestCase
{
    private MockObject&ProductStreamBuilderInterface $productStreamBuilder;

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

    private MockObject&CategoryBreadcrumbBuilder $breadcrumbBuilder;

    private ?Criteria $usedCriteria = null;

    protected function setUp(): void
    {
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
        $this->productDefinition = new ProductDefinition();
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

        $generator = $this->createGenerator();

        static::expectException(ProductExportException::class);
        static::expectExceptionMessage(ProductExportException::productExportNotFound($productExport->getId())->getMessage());

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

        $generator = $this->createGenerator();

        static::expectException(ProductExportException::class);
        static::expectExceptionMessage(ProductExportException::renderProductException($errorMessage)->getMessage());

        $generator->generate($productExport, new ExportBehavior());
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

        $this->prepareGeneratorDependencies($context, '{{ product.id }}', $product);

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

        $this->prepareGeneratorDependencies($context, '{{ product.id }}', $product);

        $this->breadcrumbBuilder->expects($this->never())->method('getProductSeoCategory');

        $this->productExportRender->expects($this->once())->method('renderBody')->willReturn('rendered');
        $this->seoUrlPlaceholderHandler->expects($this->once())->method('replace')->willReturnArgument(0);
        $this->productExportValidator->expects($this->once())->method('validate')->willReturn([]);
        $this->connection->expects($this->once())->method('delete');

        $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, false, false, false));

        static::assertNull($product->getSeoCategory());
    }

    public function testGeneratePreloadsMainCategoryAssociation(): void
    {
        // The association keeps CategoryBreadcrumbBuilder::getMainCategory() in its in-memory
        // branch instead of issuing one DAL search() per exported row.
        $productExport = $this->getProductExportEntity();
        $productExport->setFileFormat(ProductExportEntity::FILE_FORMAT_CSV);
        $productExport->setEncoding(ProductExportEntity::ENCODING_UTF8);
        $productExport->setBodyTemplate('{{ product.id }}');
        $productExport->setIncludeVariants(false);

        $context = $this->createSalesChannelContext();
        $product = $this->createProduct('product-id');

        $this->prepareGeneratorDependencies($context, '{{ product.id }}', $product);

        $this->productExportRender->method('renderBody')->willReturn('rendered');
        $this->seoUrlPlaceholderHandler->method('replace')->willReturnArgument(0);
        $this->productExportValidator->method('validate')->willReturn([]);

        $this->createGenerator()->generate($productExport, new ExportBehavior(false, false, false, false, false));

        $criteria = $this->usedCriteria;
        static::assertNotNull($criteria);
        static::assertTrue($criteria->hasAssociation('mainCategories'));
        static::assertTrue($criteria->getAssociation('mainCategories')->hasAssociation('category'));
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

    /**
     * Wires up the collaborators the happy path needs and lets the repository return
     * `$product` on the first batch and nothing on the second, which ends the read loop.
     */
    private function prepareGeneratorDependencies(
        SalesChannelContext $context,
        string $bodyTemplate,
        SalesChannelProductEntity $product
    ): void {
        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get')->willReturn($context);
        $this->languageLocaleProvider->expects($this->once())->method('getLocaleForLanguageId')->with('languageId')->willReturn('en-GB');
        $this->translator->expects($this->once())->method('injectSettings');
        $this->translator->expects($this->once())->method('resetInjection');
        $this->productStreamBuilder->expects($this->once())
            ->method('buildFilters')
            ->with('productStreamId', $context->getContext())
            ->willReturn([]);

        $twigVariableParser = $this->createMock(TwigVariableParser::class);
        $twigVariableParser->expects($this->once())->method('parse')->with($bodyTemplate)->willReturn([]);
        $this->parserFactory->expects($this->once())->method('getParser')->willReturn($twigVariableParser);

        // Once up front to guard the "no products" case, once at the end for the result total.
        $this->productRepository->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturn(new IdSearchResult(1, [['primaryKey' => $product->getId(), 'data' => []]], new Criteria(), $context->getContext()));

        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use ($product, $context): EntitySearchResult {
                $this->usedCriteria ??= $criteria;

                return $criteria->getOffset() === 0
                    ? $this->createProductSearchResult($product, $context)
                    : $this->createEmptyProductSearchResult($context);
            });
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

    private function createProduct(string $id): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($id);
        $product->setUniqueIdentifier($id);

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
    private function createProductSearchResult(SalesChannelProductEntity $product, SalesChannelContext $context): EntitySearchResult
    {
        return new EntitySearchResult(
            'product',
            1,
            new SalesChannelProductCollection([$product]),
            null,
            new Criteria([$product->getId()]),
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

    private function getProductExportEntity(): ProductExportEntity
    {
        $productExport = new ProductExportEntity();
        $productExport->setId('productExportId');
        $productExport->setCurrencyId('currencyId');
        $productExport->setSalesChannelId('salesChannelId');
        $productExport->setStorefrontSalesChannelId('storefrontSalesChannelId');
        $productExport->setProductStreamId('productStreamId');

        $salesChannelDomain = new SalesChannelDomainEntity();
        $salesChannelDomain->setLanguageId('languageId');
        $salesChannelDomain->setUrl('');
        $productExport->setSalesChannelDomain($salesChannelDomain);

        return $productExport;
    }
}
