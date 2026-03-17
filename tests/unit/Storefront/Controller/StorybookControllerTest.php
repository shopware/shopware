<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\StorybookController;
use Shopware\Storefront\Framework\Twig\Components\TwigComponent;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentCollection;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TemplateWrapper;

/**
 * @internal
 */
#[CoversClass(StorybookController::class)]
class StorybookControllerTest extends TestCase
{
    private StorybookTwigEnvironment $twig;

    /**
     * @var SalesChannelRepository<SalesChannelProductCollection>&MockObject
     */
    private SalesChannelRepository&MockObject $productRepository;

    /**
     * @var EntityRepository<MediaCollection>&MockObject
     */
    private EntityRepository&MockObject $mediaRepository;

    private AbstractSalesChannelContextFactory&MockObject $contextFactory;

    /**
     * @var EntityRepository<SalesChannelCollection>&MockObject
     */
    private EntityRepository&MockObject $salesChannelRepository;

    private DatabaseSalesChannelThemeLoader&MockObject $themeLoader;

    private ThemeRuntimeConfigStorage&MockObject $themeRuntimeConfigStorage;

    private TwigComponentHelper&MockObject $twigComponentHelper;

    protected function setUp(): void
    {
        $this->twig = new StorybookTwigEnvironment();
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->mediaRepository = $this->createMock(EntityRepository::class);
        $this->contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $this->salesChannelRepository = $this->createMock(EntityRepository::class);
        $this->themeLoader = $this->createMock(DatabaseSalesChannelThemeLoader::class);
        $this->themeRuntimeConfigStorage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $this->twigComponentHelper = $this->createMock(TwigComponentHelper::class);
    }

    public function testStorybookThrowsNotFoundInNonDevEnvironment(): void
    {
        $controller = $this->createController('prod');

        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:6006');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Route not found');

        $controller->storybook('my-component', $request);
    }

    public function testStorybookThrowsNotFoundWhenOriginHeaderIsMissing(): void
    {
        $controller = $this->createController('dev');

        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Route not found');

        $controller->storybook('my-component', $request);
    }

    public function testStorybookThrowsNotFoundWhenOriginIsWrong(): void
    {
        $controller = $this->createController('dev');

        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:3000');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Route not found');

        $controller->storybook('my-component', $request);
    }

    public function testStorybookThrowsNotFoundWhenComponentNotRegistered(): void
    {
        $this->twigComponentHelper->method('getComponents')
            ->willReturn(new TwigComponentCollection());

        $controller = $this->createController('dev');

        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:6006');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Component not found');

        $controller->storybook('unknown-component', $request);
    }

    public function testStorybookThrowsSalesChannelExceptionWhenNoneAvailable(): void
    {
        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->salesChannelRepository->method('search')
            ->willReturn(new EntitySearchResult(
                SalesChannelDefinition::ENTITY_NAME,
                0,
                new SalesChannelCollection(),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));

        $controller = $this->createController('dev');

        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:6006');

        $this->expectException(SalesChannelException::class);

        $controller->storybook('my-button', $request);
    }

    public function testStorybookRendersComponentSuccessfully(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->salesChannelRepository->method('search')
            ->willReturn($this->createSalesChannelSearchResult($salesChannelId));

        $this->contextFactory->method('create')
            ->with('', $salesChannelId)
            ->willReturn($salesChannelContext);

        $this->themeLoader->method('load')
            ->with($salesChannelId)
            ->willReturn(['Storefront']);

        $this->themeRuntimeConfigStorage->method('getThemeIdByTechnicalName')
            ->with('Storefront')
            ->willReturn('theme-id-123');

        $this->twig->renderOutput = '<div>rendered component</div>';

        $controller = $this->createController('dev');

        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:6006');

        $response = $controller->storybook('my-button', $request);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('<div>rendered component</div>', (string) $response->getContent());
        static::assertSame('http://localhost:6006', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testStorybookSetsThemeIdAndSalesChannelIdOnRequestAttributes(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->salesChannelRepository->method('search')
            ->willReturn($this->createSalesChannelSearchResult($salesChannelId));

        $this->contextFactory->method('create')->willReturn($salesChannelContext);

        $this->themeLoader->method('load')->willReturn(['Storefront']);
        $this->themeRuntimeConfigStorage->method('getThemeIdByTechnicalName')->willReturn('theme-id-123');

        $controller = $this->createController('dev');

        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:6006');

        $controller->storybook('my-button', $request);

        static::assertSame('theme-id-123', $request->attributes->get('theme-id'));
        static::assertSame($salesChannelId, $request->attributes->get('sw-sales-channel-id'));
    }

    public function testStorybookSetsContextAndThemeIdAsGlobalsOnTwig(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->salesChannelRepository->method('search')
            ->willReturn($this->createSalesChannelSearchResult($salesChannelId));

        $this->contextFactory->method('create')->willReturn($salesChannelContext);
        $this->themeLoader->method('load')->willReturn(['Storefront']);
        $this->themeRuntimeConfigStorage->method('getThemeIdByTechnicalName')->willReturn('theme-id-123');

        $controller = $this->createController('dev');

        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:6006');

        $controller->storybook('my-button', $request);

        static::assertSame($salesChannelContext, $this->twig->globals['context']);
        static::assertSame('theme-id-123', $this->twig->globals['themeId']);
    }

    public function testStorybookReturns500OnTwigRuntimeError(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->salesChannelRepository->method('search')
            ->willReturn($this->createSalesChannelSearchResult($salesChannelId));

        $this->contextFactory->method('create')->willReturn($salesChannelContext);
        $this->themeLoader->method('load')->willReturn([]);

        $this->twig->renderException = new \Twig\Error\RuntimeError('Template rendering failed');

        $controller = $this->createController('dev');

        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:6006');

        $response = $controller->storybook('my-button', $request);

        $content = $response->getContent();
        static::assertSame(500, $response->getStatusCode());
        static::assertIsString($content);
        static::assertStringContainsString('Template error:', $content);
        static::assertStringContainsString('Template rendering failed', $content);
        static::assertSame('http://localhost:6006', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testStorybookReturns500OnTwigSyntaxError(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->salesChannelRepository->method('search')
            ->willReturn($this->createSalesChannelSearchResult($salesChannelId));

        $this->contextFactory->method('create')->willReturn($salesChannelContext);
        $this->themeLoader->method('load')->willReturn([]);

        $this->twig->createTemplateException = new \Twig\Error\SyntaxError('Unexpected token');

        $controller = $this->createController('dev');

        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:6006');

        $response = $controller->storybook('my-button', $request);

        $content = $response->getContent();
        static::assertSame(500, $response->getStatusCode());
        static::assertIsString($content);
        static::assertStringContainsString('Template error:', $content);
        static::assertStringContainsString('Unexpected token', $content);
    }

    public function testStorybookFiltersDenyListedQueryParams(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->salesChannelRepository->method('search')
            ->willReturn($this->createSalesChannelSearchResult($salesChannelId));

        $this->contextFactory->method('create')->willReturn($salesChannelContext);
        $this->themeLoader->method('load')->willReturn([]);

        $capturedProps = [];
        $this->twig->renderCallback = static function (mixed $template, array $context) use (&$capturedProps): string {
            $capturedProps = $context['componentProps'] ?? [];

            return '';
        };

        $controller = $this->createController('dev');

        $request = new Request([
            'label' => 'Click me',
            'measureEnabled' => 'true',
            'backgrounds' => 'dark',
            'outline' => '1',
            'viewport' => 'mobile',
        ]);
        $request->headers->set('Origin', 'http://localhost:6006');

        $controller->storybook('my-button', $request);

        static::assertArrayHasKey('label', $capturedProps);
        static::assertSame('Click me', $capturedProps['label']);
        static::assertArrayNotHasKey('measureEnabled', $capturedProps);
        static::assertArrayNotHasKey('backgrounds', $capturedProps);
        static::assertArrayNotHasKey('outline', $capturedProps);
        static::assertArrayNotHasKey('viewport', $capturedProps);
    }

    public function testStorybookFiltersInvalidQueryParamIdentifiers(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->salesChannelRepository->method('search')
            ->willReturn($this->createSalesChannelSearchResult($salesChannelId));

        $this->contextFactory->method('create')->willReturn($salesChannelContext);
        $this->themeLoader->method('load')->willReturn([]);

        $capturedProps = [];
        $this->twig->renderCallback = static function (mixed $template, array $context) use (&$capturedProps): string {
            $capturedProps = $context['componentProps'] ?? [];

            return '';
        };

        $controller = $this->createController('dev');

        $request = new Request([
            'validProp' => 'hello',
            '123invalid' => 'bad',
            'also-invalid' => 'bad',
            'valid_prop2' => 'world',
        ]);
        $request->headers->set('Origin', 'http://localhost:6006');

        $controller->storybook('my-button', $request);

        static::assertArrayHasKey('validProp', $capturedProps);
        static::assertArrayHasKey('valid_prop2', $capturedProps);
        static::assertArrayNotHasKey('123invalid', $capturedProps);
        static::assertArrayNotHasKey('also-invalid', $capturedProps);
    }

    public function testStorybookResolvesProductEntityProperty(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('product-card'));

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSalesChannelSearchResult($salesChannelId));

        $this->contextFactory->method('create')->willReturn($salesChannelContext);
        $this->themeLoader->method('load')->willReturn([]);

        $product = new SalesChannelProductEntity();
        $product->setId('product-id-123');
        $product->setUniqueIdentifier('product-id-123');

        $this->productRepository->method('search')
            ->willReturn(new EntitySearchResult(
                ProductDefinition::ENTITY_NAME,
                1,
                new SalesChannelProductCollection([$product]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $capturedProps = [];
        $this->twig->renderCallback = static function (mixed $template, array $context) use (&$capturedProps): string {
            $capturedProps = $context['componentProps'] ?? [];

            return '';
        };

        $controller = $this->createController('dev');

        $request = new Request(['product' => 'product']);
        $request->headers->set('Origin', 'http://localhost:6006');

        $controller->storybook('product-card', $request);

        static::assertArrayHasKey('product', $capturedProps);
        static::assertSame($product, $capturedProps['product']);
    }

    public function testStorybookResolvesMediaEntityProperty(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('media-card'));

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createSalesChannelSearchResult($salesChannelId));

        $this->contextFactory->method('create')->willReturn($salesChannelContext);
        $this->themeLoader->method('load')->willReturn([]);

        $media = new MediaEntity();
        $media->setId('media-id-123');
        $media->setUniqueIdentifier('media-id-123');

        $this->mediaRepository->method('search')
            ->willReturn(new EntitySearchResult(
                MediaDefinition::ENTITY_NAME,
                1,
                new MediaCollection([$media]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $capturedProps = [];
        $this->twig->renderCallback = static function (mixed $template, array $context) use (&$capturedProps): string {
            $capturedProps = $context['componentProps'] ?? [];

            return '';
        };

        $controller = $this->createController('dev');

        $request = new Request(['media' => 'media']);
        $request->headers->set('Origin', 'http://localhost:6006');

        $controller->storybook('media-card', $request);

        static::assertArrayHasKey('media', $capturedProps);
        static::assertSame($media, $capturedProps['media']);
    }

    public function testStorybookReturnsNullForProductWhenRepositoryIsEmpty(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('product-card'));

        $this->salesChannelRepository->method('search')
            ->willReturn($this->createSalesChannelSearchResult($salesChannelId));

        $this->contextFactory->method('create')->willReturn($salesChannelContext);
        $this->themeLoader->method('load')->willReturn([]);

        $this->productRepository->method('search')
            ->willReturn(new EntitySearchResult(
                ProductDefinition::ENTITY_NAME,
                0,
                new SalesChannelProductCollection(),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $capturedProps = [];
        $this->twig->renderCallback = static function (mixed $template, array $context) use (&$capturedProps): string {
            $capturedProps = $context['componentProps'] ?? [];

            return '';
        };

        $controller = $this->createController('dev');

        $request = new Request(['product' => 'product']);
        $request->headers->set('Origin', 'http://localhost:6006');

        $controller->storybook('product-card', $request);

        static::assertArrayHasKey('product', $capturedProps);
        static::assertNull($capturedProps['product']);
    }

    public function testStorybookUsesNullThemeIdWhenThemeLoaderReturnsEmpty(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->salesChannelRepository->method('search')
            ->willReturn($this->createSalesChannelSearchResult($salesChannelId));

        $this->contextFactory->method('create')->willReturn($salesChannelContext);
        $this->themeLoader->method('load')->willReturn([]);

        $this->themeRuntimeConfigStorage->expects($this->never())
            ->method('getThemeIdByTechnicalName');

        $controller = $this->createController('dev');

        $request = new Request();
        $request->headers->set('Origin', 'http://localhost:6006');

        $controller->storybook('my-button', $request);

        static::assertArrayHasKey('themeId', $this->twig->globals);
        static::assertNull($this->twig->globals['themeId']);
    }

    private function createController(string $environment): StorybookController
    {
        return new StorybookController(
            $environment,
            $this->twig,
            $this->productRepository,
            $this->mediaRepository,
            $this->contextFactory,
            $this->salesChannelRepository,
            $this->themeLoader,
            $this->themeRuntimeConfigStorage,
            $this->twigComponentHelper,
        );
    }

    private function createCollectionWithComponent(string $componentName): TwigComponentCollection
    {
        $component = new TwigComponent($componentName, '/path/to/' . $componentName . '.html.twig', 'Storefront');

        return new TwigComponentCollection([$component]);
    }

    /**
     * @return EntitySearchResult<SalesChannelCollection>
     */
    private function createSalesChannelSearchResult(string $salesChannelId): EntitySearchResult
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setUniqueIdentifier($salesChannelId);

        return new EntitySearchResult(
            SalesChannelDefinition::ENTITY_NAME,
            1,
            new SalesChannelCollection([$salesChannel]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}

/**
 * @internal
 *
 * A test-specific Twig Environment that avoids mocking the final TemplateWrapper class.
 */
class StorybookTwigEnvironment extends Environment
{
    public string $renderOutput = '';

    public ?\Throwable $renderException = null;

    public ?\Throwable $createTemplateException = null;

    /**
     * @var \Closure(string|TemplateWrapper, array<string, mixed>): string|null
     */
    public ?\Closure $renderCallback = null;

    /**
     * @var array<string, mixed>
     */
    public array $globals = [];

    public function __construct()
    {
        parent::__construct(new ArrayLoader([]));
    }

    public function addGlobal(string $name, mixed $value): void
    {
        $this->globals[$name] = $value;
    }

    public function createTemplate(string $template, ?string $name = null): TemplateWrapper
    {
        if ($this->createTemplateException !== null) {
            throw $this->createTemplateException;
        }

        return parent::createTemplate('');
    }

    /**
     * @param string|TemplateWrapper $name
     * @param array<string, mixed> $context
     */
    public function render($name, array $context = []): string
    {
        if ($this->renderException !== null) {
            throw $this->renderException;
        }

        if ($this->renderCallback !== null) {
            return ($this->renderCallback)($name, $context);
        }

        return $this->renderOutput;
    }
}
