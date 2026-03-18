<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\StorybookController;
use Shopware\Storefront\Framework\Twig\Components\TwigComponent;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentCollection;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Shopware\Storefront\Storybook\StorybookService;
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

    private TwigComponentHelper&MockObject $twigComponentHelper;

    private StorybookService&MockObject $storybookService;

    protected function setUp(): void
    {
        $this->twig = new StorybookTwigEnvironment();
        $this->twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $this->storybookService = $this->createMock(StorybookService::class);
    }

    public function testStorybookThrowsNotFoundInNonDevEnvironment(): void
    {
        $controller = $this->createController('prod');

        $this->expectException(NotFoundHttpException::class);

        $controller->storybook('my-component', new Request());
    }

    public function testStorybookThrowsNotFoundWhenComponentNotRegistered(): void
    {
        $this->twigComponentHelper->method('getComponents')
            ->willReturn(new TwigComponentCollection());

        $controller = $this->createController('dev');

        $this->expectException(NotFoundHttpException::class);

        $controller->storybook('unknown-component', new Request());
    }

    public function testStorybookRendersComponentSuccessfully(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')
            ->willReturn('theme-id-123');

        $this->storybookService->method('resolveComponentProps')
            ->willReturn(['label' => 'Click me']);

        $this->twig->renderOutput = '<div>rendered component</div>';

        $response = $this->createController('dev')->storybook('my-button', new Request());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('<div>rendered component</div>', $response->getContent());
        static::assertSame('http://localhost:6006', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testStorybookSetsThemeIdAndSalesChannelIdOnRequestAttributes(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')
            ->with($salesChannelId)
            ->willReturn('theme-id-123');

        $request = new Request();

        $this->createController('dev')->storybook('my-button', $request);

        static::assertSame('theme-id-123', $request->attributes->get('theme-id'));
        static::assertSame($salesChannelId, $request->attributes->get('sw-sales-channel-id'));
    }

    public function testStorybookSetsContextAndThemeIdAsGlobalsOnTwig(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')
            ->willReturn('theme-id-123');

        $this->createController('dev')->storybook('my-button', new Request());

        static::assertSame($salesChannelContext, $this->twig->globals['context']);
        static::assertSame('theme-id-123', $this->twig->globals['themeId']);
    }

    public function testStorybookReturns500OnTwigRuntimeError(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')->willReturn(null);
        $this->storybookService->method('resolveComponentProps')->willReturn([]);

        $this->twig->renderException = new \Twig\Error\RuntimeError('Template rendering failed');

        $response = $this->createController('dev')->storybook('my-button', new Request());

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

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')->willReturn(null);
        $this->storybookService->method('resolveComponentProps')->willReturn([]);

        $this->twig->createTemplateException = new \Twig\Error\SyntaxError('Unexpected token');

        $response = $this->createController('dev')->storybook('my-button', new Request());

        $content = $response->getContent();
        static::assertSame(500, $response->getStatusCode());
        static::assertIsString($content);
        static::assertStringContainsString('Template error:', $content);
        static::assertStringContainsString('Unexpected token', $content);
    }

    public function testStorybookPassesResolvedPropsToTemplate(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $expectedProps = ['label' => 'Click me', 'disabled' => 'true'];

        $this->twigComponentHelper->method('getComponents')
            ->willReturn($this->createCollectionWithComponent('my-button'));

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')->willReturn(null);

        $this->storybookService->method('resolveComponentProps')
            ->willReturn($expectedProps);

        $capturedProps = [];
        $this->twig->renderCallback = static function (mixed $template, array $context) use (&$capturedProps): string {
            $capturedProps = $context['componentProps'] ?? [];

            return '';
        };

        $this->createController('dev')->storybook('my-button', new Request());

        static::assertSame($expectedProps, $capturedProps);
    }

    private function createController(string $environment): StorybookController
    {
        return new StorybookController(
            $environment,
            $this->twig,
            $this->twigComponentHelper,
            $this->storybookService,
        );
    }

    private function createCollectionWithComponent(string $componentName): TwigComponentCollection
    {
        $component = new TwigComponent($componentName, '/path/to/' . $componentName . '.html.twig', 'Storefront');

        return new TwigComponentCollection([$component]);
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
