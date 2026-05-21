<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\StorybookController;
use Shopware\Storefront\Storybook\StorybookService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\TemplateWrapper;

/**
 * @internal
 */
#[CoversClass(StorybookController::class)]
class StorybookControllerTest extends TestCase
{
    use EnvTestBehaviour;

    private const STORYBOOK_ORIGIN = 'http://localhost:6006';

    private StorybookTwigEnvironment $twig;

    private StorybookService&MockObject $storybookService;

    protected function setUp(): void
    {
        $this->twig = new StorybookTwigEnvironment();
        $this->storybookService = $this->createMock(StorybookService::class);
    }

    public function testStorybookRendersComponentSuccessfully(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')
            ->willReturn('theme-id-123');

        $this->storybookService->method('resolveComponentProps')
            ->willReturn(['label' => 'Click me']);

        $this->twig->renderOutput = '<div>rendered component</div>';

        $response = $this->createController()->storybook('my-button', $this->createStorybookRequest());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('<div>rendered component</div>', $response->getContent());
        static::assertSame('http://localhost:6006', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testStorybookSetsThemeIdAndSalesChannelIdOnRequestAttributes(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')
            ->with($salesChannelId)
            ->willReturn('theme-id-123');

        $request = $this->createStorybookRequest();

        $this->createController()->storybook('my-button', $request);

        static::assertSame('theme-id-123', $request->attributes->get('theme-id'));
        static::assertSame($salesChannelId, $request->attributes->get('sw-sales-channel-id'));
    }

    public function testStorybookPassesContextAndThemeIdViaRenderContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')
            ->willReturn('theme-id-123');

        $this->createController()->storybook('my-button', $this->createStorybookRequest());

        static::assertSame($salesChannelContext, $this->twig->renderContext['context']);
        static::assertSame('theme-id-123', $this->twig->renderContext['themeId']);
    }

    public function testStorybookDoesNotLeakContextAndThemeIdIntoTwigGlobalsAcrossRequests(): void
    {
        $firstContext = Generator::generateSalesChannelContext();
        $secondContext = Generator::generateSalesChannelContext();

        $this->storybookService->expects($this->exactly(2))
            ->method('createSalesChannelContext')
            ->willReturnOnConsecutiveCalls($firstContext, $secondContext);

        $this->storybookService->expects($this->exactly(2))
            ->method('getThemeId')
            ->willReturnOnConsecutiveCalls('theme-id-1', 'theme-id-2');

        $this->storybookService->expects($this->exactly(2))
            ->method('resolveComponentProps')
            ->willReturn([]);

        $controller = $this->createController();
        $controller->storybook('my-button', $this->createStorybookRequest());
        $controller->storybook('my-button', $this->createStorybookRequest());

        static::assertArrayNotHasKey('context', $this->twig->globals);
        static::assertArrayNotHasKey('themeId', $this->twig->globals);
        static::assertSame($secondContext, $this->twig->renderContext['context']);
        static::assertSame('theme-id-2', $this->twig->renderContext['themeId']);
    }

    public function testStorybookReturns500OnTwigRuntimeError(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')->willReturn(null);
        $this->storybookService->method('resolveComponentProps')->willReturn([]);

        $this->twig->renderException = new RuntimeError('Template rendering failed');

        $response = $this->createController()->storybook('my-button', $this->createStorybookRequest());

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

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')->willReturn(null);
        $this->storybookService->method('resolveComponentProps')->willReturn([]);

        $this->twig->createTemplateException = new SyntaxError('Unexpected token');

        $response = $this->createController()->storybook('my-button', $this->createStorybookRequest());

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

        $this->createController()->storybook('my-button', $this->createStorybookRequest());

        static::assertSame($expectedProps, $capturedProps);
    }

    public function testStorybookUsesCustomDomainFromEnvVariable(): void
    {
        $customDomain = 'http://my-dev-store.example.com:6006';
        $this->setEnvVars(['STORYBOOK_DOMAIN' => $customDomain]);

        $salesChannelContext = Generator::generateSalesChannelContext();

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')->willReturn(null);
        $this->storybookService->method('resolveComponentProps')->willReturn([]);

        $request = new Request();
        $request->headers->set('Origin', $customDomain);

        $response = $this->createController()->storybook('my-button', $request);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame($customDomain, $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testStorybookRejectsRequestWhenOriginDoesNotMatchCustomDomain(): void
    {
        $this->setEnvVars(['STORYBOOK_DOMAIN' => 'http://my-dev-store.example.com:6006']);

        $controller = $this->createController();

        $this->expectException(NotFoundHttpException::class);

        $controller->storybook('my-button', $this->createStorybookRequest());
    }

    private function createStorybookRequest(): Request
    {
        $request = new Request();
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);

        return $request;
    }

    private function createController(): StorybookController
    {
        return new StorybookController(
            $this->twig,
            $this->storybookService,
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

    /**
     * @var array<string, mixed>
     */
    public array $renderContext = [];

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
        $this->renderContext = $context;

        if ($this->renderException !== null) {
            throw $this->renderException;
        }

        if ($this->renderCallback !== null) {
            return ($this->renderCallback)($name, $context);
        }

        return $this->renderOutput;
    }
}
