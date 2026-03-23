<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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
    }

    public function testGenerateWithInvalidProductExportId(): void
    {
        $productExport = $this->getProductExportEntity();

        $this->contextPersister->expects($this->once())->method('save');
        $this->salesChannelContextService->expects($this->once())->method('get');
        $this->parserFactory->expects($this->once())->method('getParser');

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

        static::expectException(ProductExportException::class);
        static::expectExceptionMessage(ProductExportException::renderProductException($errorMessage)->getMessage());

        $generator->generate($productExport, new ExportBehavior());
    }

    public function testGenerateNormalizesJsonlRows(): void
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
            ->method('searchIds')
            ->willReturnCallback(static function (Criteria $criteria, SalesChannelContext $salesChannelContext) use ($context): IdSearchResult {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $criteria->getTotalCountMode());
                static::assertSame($context, $salesChannelContext);

                return IdSearchResult::fromIds(['product-id'], $criteria, $context->getContext());
            });

        $this->productRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->createProductSearchResult($product, $context),
                $this->createEmptyProductSearchResult($context)
            );

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
            ->method('searchIds')
            ->willReturnCallback(static function (Criteria $criteria, SalesChannelContext $salesChannelContext) use ($context): IdSearchResult {
                static::assertSame($context, $salesChannelContext);

                return IdSearchResult::fromIds(['product-id'], $criteria, $context->getContext());
            });

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

        static::expectException(ProductExportException::class);
        static::expectExceptionMessage('The JSONL row for product export "productExportId" could not be normalized');

        $generator->generate($productExport, new ExportBehavior(false, false, false, false, false));
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
            $this->parserFactory
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
        $product->setParentId(null);
        $product->setChildCount(0);

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
