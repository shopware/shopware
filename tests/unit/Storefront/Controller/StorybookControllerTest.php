<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\StorybookController;
use Shopware\Storefront\Storybook\StorybookService;
use Shopware\Tests\Unit\Storefront\Controller\Stub\StorybookTwigEnvironmentStub;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\UX\TwigComponent\ComponentFactory;
use Symfony\UX\TwigComponent\ComponentTemplateFinderInterface;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorybookController::class)]
class StorybookControllerTest extends TestCase
{
    use EnvTestBehaviour;

    private const STORYBOOK_ORIGIN = 'http://localhost:6006';

    private StorybookTwigEnvironmentStub $twig;

    private StorybookService&Stub $storybookService;

    private ComponentFactory $componentFactory;

    private HtmlSanitizer&Stub $htmlSanitizer;

    protected function setUp(): void
    {
        $this->twig = new StorybookTwigEnvironmentStub();
        $componentConfig = [];
        foreach (['my-button', 'Sw:Button', 'Sw:Content:Modal', 'Sw:Alert'] as $componentName) {
            $componentConfig[$componentName] = ['key' => $componentName, 'template' => 'storybook-component'];
        }
        $componentConfig['Sw:Inheriting:Component'] = [
            'key' => 'Sw:Inheriting:Component',
            'template' => 'storybook-inherited-component',
        ];
        $componentConfig['Sw:Missing:Template'] = [
            'key' => 'Sw:Missing:Template',
            'template' => 'storybook-missing-template',
        ];
        $this->componentFactory = new ComponentFactory(
            static::createStub(ComponentTemplateFinderInterface::class),
            new ServiceLocator([]),
            static::createStub(PropertyAccessorInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $componentConfig,
            [],
            $this->twig,
        );
        $this->storybookService = static::createStub(StorybookService::class);
        $this->htmlSanitizer = static::createStub(HtmlSanitizer::class);
        $this->htmlSanitizer->method('sanitize')->willReturnCallback(static fn (string $html): string => $html);
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

    public function testStorybookPassesBlockContentSeparatelyToTwigComponent(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $this->storybookService->method('createSalesChannelContext')->willReturn($salesChannelContext);
        $this->storybookService->method('getThemeId')->willReturn('theme-id-123');
        $this->storybookService->method('resolveComponentProps')->willReturn([
            'content' => 'Prop value',
            'storybookBlockContent' => 'Ordinary prop',
        ]);

        $request = $this->createStorybookRequest();
        $request->initialize(content: '{"props":{"content":"Prop value","storybookBlockContent":"Ordinary prop"},"blocks":{"content":"<strong>Block value</strong>","title":"Title block"}}');
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);

        $response = $this->createController()->storybook('Sw:Button', $request);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['content' => '<strong>Block value</strong>', 'title' => 'Title block'], $this->twig->renderContext['blockContent']);
        static::assertStringContainsString('<twig:block name="content">', $this->twig->createdTemplate);
        static::assertStringContainsString('<twig:block name="title">', $this->twig->createdTemplate);
        static::assertSame('Prop value', $this->twig->renderContext['componentProps']['content']);
        static::assertSame('Ordinary prop', $this->twig->renderContext['componentProps']['storybookBlockContent']);
    }

    public function testStorybookRejectsInvalidNamedBlockNames(): void
    {
        $request = $this->createStorybookRequest();
        $request->initialize(content: '{"props":{},"blocks":{"title|raw":"unsafe"}}');
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);

        $response = $this->createController()->storybook('my-button', $request);

        static::assertSame(400, $response->getStatusCode());
        static::assertSame('The Storybook preview payload must contain string block content and property data.', $response->getContent());
    }

    public function testStorybookReturnsClearErrorForUnknownComponentBlock(): void
    {
        $request = $this->createStorybookRequest();
        $request->initialize(content: '{"props":{},"blocks":{"typo":"<strong>Unknown</strong>"}}');
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);

        $response = $this->createController()->storybook('Sw:Button', $request);

        static::assertSame(400, $response->getStatusCode());
        static::assertSame('Unknown block name(s) for component "Sw:Button": typo.', $response->getContent());
    }

    public function testStorybookReturnsBadRequestForUnknownComponent(): void
    {
        $request = $this->createStorybookRequest();
        $request->initialize(content: '{"props":{},"blocks":{"content":"<strong>Example</strong>"}}');
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);

        $response = $this->createController()->storybook('Sw:NotRegistered', $request);

        static::assertSame(400, $response->getStatusCode());
        static::assertSame('Unknown Storybook component "Sw:NotRegistered".', $response->getContent());
        static::assertSame(self::STORYBOOK_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testStorybookReturnsBadRequestForUnknownComponentWithoutBlocks(): void
    {
        $response = $this->createController()->storybook('Sw:NotRegistered', $this->createStorybookRequest());

        static::assertSame(400, $response->getStatusCode());
        static::assertSame('Unknown Storybook component "Sw:NotRegistered".', $response->getContent());
        static::assertSame(self::STORYBOOK_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testStorybookReturnsPlainTextErrorForMissingComponentTemplate(): void
    {
        $this->storybookService->method('createSalesChannelContext')->willReturn(Generator::generateSalesChannelContext());
        $this->storybookService->method('getThemeId')->willReturn('theme-id-123');
        $this->storybookService->method('resolveComponentProps')->willReturn([]);

        $request = $this->createStorybookRequest();
        $request->initialize(content: '{"props":{},"blocks":{"header":"<strong>Example</strong>"}}');
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);

        $response = $this->createController()->storybook('Sw:Missing:Template', $request);

        static::assertSame(500, $response->getStatusCode());
        static::assertStringContainsString('Template error:', (string) $response->getContent());
        static::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        static::assertSame(self::STORYBOOK_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testStorybookAcceptsBlockInheritedByComponentTemplate(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $this->storybookService->method('createSalesChannelContext')->willReturn($salesChannelContext);
        $this->storybookService->method('getThemeId')->willReturn('theme-id-123');
        $this->storybookService->method('resolveComponentProps')->willReturn([]);

        $request = $this->createStorybookRequest();
        $request->initialize(content: '{"props":{},"blocks":{"header":"<strong>Storybook header</strong>"}}');
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);

        $response = $this->createController()->storybook('Sw:Inheriting:Component', $request);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['header' => '<strong>Storybook header</strong>'], $this->twig->renderContext['blockContent']);
        static::assertStringContainsString('<twig:block name="header">', $this->twig->createdTemplate);
    }

    public function testStorybookSanitizesOnlyBlockContentBeforeRendering(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $this->storybookService->method('createSalesChannelContext')->willReturn($salesChannelContext);
        $this->storybookService->method('getThemeId')->willReturn('theme-id-123');
        $this->storybookService->method('resolveComponentProps')->willReturn(['title' => '<script>Prop value</script>']);
        $this->twig->renderOutput = '<svg><path d="M0 0"/></svg><link rel="stylesheet" href="/theme.css">';
        $request = $this->createStorybookRequest();
        $request->initialize(content: '{"props":{"title":"<script>Prop value</script>"},"blocks":{"content":"<strong>Block</strong><script>unsafe</script>"}}');
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);
        $request->headers->set('Host', 'localhost');

        $sanitizer = static::createStub(HtmlSanitizer::class);
        $sanitizedInput = [];
        $sanitizer->method('sanitize')
            ->willReturnCallback(static function (string $html, ?array $options = [], bool $override = false, ?string $field = null) use (&$sanitizedInput): string {
                $sanitizedInput = [$html, $options, $override, $field];

                return '<strong>Block</strong>';
            });

        $response = $this->createController(htmlSanitizer: $sanitizer)
            ->storybook('Sw:Content:Modal', $request);

        static::assertSame('<svg><path d="M0 0"/></svg><link rel="stylesheet" href="/theme.css">', $response->getContent());
        static::assertSame(['<strong>Block</strong><script>unsafe</script>', [], false, 'storybook'], $sanitizedInput);
        static::assertSame(['content' => '<strong>Block</strong>'], $this->twig->renderContext['blockContent']);
        static::assertSame('<script>Prop value</script>', $this->twig->renderContext['componentProps']['title']);
    }

    public function testStorybookDoesNotSanitizePropOnlyPreview(): void
    {
        $this->storybookService->method('createSalesChannelContext')->willReturn(Generator::generateSalesChannelContext());
        $this->storybookService->method('getThemeId')->willReturn(null);
        $this->storybookService->method('resolveComponentProps')->willReturn(['title' => '<span>Title</span>']);
        $this->twig->renderOutput = '<svg><path d="M0 0"/></svg>';

        $sanitizer = static::createStub(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willThrowException(new \RuntimeException('Unexpected sanitization'));

        $response = $this->createController(htmlSanitizer: $sanitizer)
            ->storybook('Sw:Content:Modal', $this->createStorybookRequest());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('<svg><path d="M0 0"/></svg>', $response->getContent());
    }

    public function testStorybookPropagatesSanitizerFailure(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $this->storybookService->method('createSalesChannelContext')->willReturn($salesChannelContext);
        $this->storybookService->method('getThemeId')->willReturn(null);
        $this->storybookService->method('resolveComponentProps')->willReturn([]);
        $this->twig->renderOutput = '<div>unsafe preview</div>';

        $request = $this->createStorybookRequest();
        $request->initialize(content: '{"props":{},"blocks":{"content":"<script>unsafe</script>"}}');
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);

        $sanitizer = static::createStub(HtmlSanitizer::class);
        $failure = new \RuntimeException('HTMLPurifier failed');
        $sanitizer->method('sanitize')->willThrowException($failure);

        try {
            $this->createController(htmlSanitizer: $sanitizer)->storybook('my-button', $request);
            static::fail('Expected sanitization to fail.');
        } catch (\RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }
    }

    public function testStorybookRejectsNonStringBlockContent(): void
    {
        $request = $this->createStorybookRequest();
        $request->initialize(content: '{"props":{},"blocks":{"content":["unexpected"]}}');
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);

        $response = $this->createController()->storybook('my-button', $request);

        static::assertSame(400, $response->getStatusCode());
        static::assertSame('The Storybook preview payload must contain string block content and property data.', $response->getContent());
    }

    public function testStorybookRejectsInvalidJsonPayload(): void
    {
        $request = $this->createStorybookRequest();
        $request->initialize(content: '{invalid json');
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);

        $response = $this->createController()->storybook('my-button', $request);

        static::assertSame(400, $response->getStatusCode());
        static::assertSame('The Storybook preview payload must contain string block content and property data.', $response->getContent());
    }

    public function testStorybookSetsThemeIdAndSalesChannelIdOnRequestAttributes(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->storybookService->method('createSalesChannelContext')
            ->willReturn($salesChannelContext);

        $this->storybookService->method('getThemeId')
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

        $storybookService = $this->createMock(StorybookService::class);
        $storybookService->expects($this->exactly(2))
            ->method('createSalesChannelContext')
            ->willReturnOnConsecutiveCalls($firstContext, $secondContext);

        $storybookService->expects($this->exactly(2))
            ->method('getThemeId')
            ->willReturnOnConsecutiveCalls('theme-id-1', 'theme-id-2');

        $storybookService->expects($this->exactly(2))
            ->method('resolveComponentProps')
            ->willReturn([]);

        $controller = $this->createController($storybookService);
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

    public function testStorybookRejectsInvalidComponentName(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->storybook('my-button><script>', $this->createStorybookRequest());
    }

    private function createStorybookRequest(): Request
    {
        $request = new Request();
        $request->headers->set('Origin', self::STORYBOOK_ORIGIN);

        return $request;
    }

    private function createController(
        ?StorybookService $storybookService = null,
        ?HtmlSanitizer $htmlSanitizer = null,
    ): StorybookController {
        return new StorybookController(
            $this->twig,
            $this->componentFactory,
            $storybookService ?? $this->storybookService,
            $htmlSanitizer ?? $this->htmlSanitizer,
        );
    }
}
