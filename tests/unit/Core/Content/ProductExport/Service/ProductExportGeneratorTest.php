<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
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

    private ProductExportValidatorInterface $productExportValidator;

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

        $this->contextPersister->expects(static::once())->method('save');
        $this->salesChannelContextService->expects(static::once())->method('get');
        $this->parserFactory->expects(static::once())->method('getParser');

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

        $this->contextPersister->expects(static::once())->method('save');
        $this->salesChannelContextService->expects(static::once())->method('get');

        $errorMessage = 'error message';
        $twigVariableParser = $this->createMock(TwigVariableParser::class);
        $twigVariableParser->method('parse')
            ->willThrowException(new \Exception($errorMessage));
        $this->parserFactory->expects(static::once())
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
        $productExport->setSalesChannelDomain($salesChannelDomain);

        return $productExport;
    }
}
