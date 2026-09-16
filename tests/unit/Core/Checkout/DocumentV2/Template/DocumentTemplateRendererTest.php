<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Template\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures\StaticDocumentSource;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Extension\CoreExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentTemplateRenderer::class)]
class DocumentTemplateRendererTest extends TestCase
{
    public function testRender(): void
    {
        $context = Context::createDefaultContext();
        $template = 'rendered template';

        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode('en-GB');

        $lang = new LanguageEntity();
        $lang->setId(Uuid::randomHex());
        $lang->setLocale($locale);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId(Uuid::randomHex());
        $order->setLanguageId(Uuid::randomHex());
        $order->setLanguage($lang);

        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects($this->once())->method('resetInjection');
        $translator->expects($this->once())
            ->method('injectSettings')
            ->with(
                $order->getSalesChannelId(),
                $order->getLanguageId(),
                $locale->getCode(),
            );

        $finder = $this->createMock(TemplateFinder::class);
        $finder->expects($this->once())->method('reset');
        $finder->expects($this->once())
            ->method('find')
            ->willReturn('path');

        $env = $this->createMock(TwigEnvironment::class);
        $env->expects($this->once())
            ->method('renderWithTimezoneOverride')
            ->with(
                'path',
                static::callback(function (array $parameters) use ($order) {
                    return $parameters['order'] === $order
                        && $parameters['documentNumber'] === '12345'
                        && $parameters['rootDir'] === 'rootDir'
                        && $parameters['documentV2'] === true
                        && !\array_key_exists('counter', $parameters)
                        && $parameters['context'] instanceof SalesChannelContext;
                }),
                null,
            )
            ->willReturn($template);

        $salesChannel = new SalesChannelEntity();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        $contextFactory = static::createStub(AbstractSalesChannelContextFactory::class);
        $contextFactory->method('create')->willReturn($salesChannelContext);

        $renderer = new DocumentTemplateRenderer(
            $finder,
            $env,
            $translator,
            $contextFactory,
            new EventDispatcher(),
            'rootDir',
        );

        $input = new RenderInput(
            DocumentType::INVOICE->value,
            '12345',
            $order,
        );

        $result = $renderer->render(
            'path',
            $input,
            $context,
        );

        static::assertIsString($result);
        static::assertSame($template, $result);
    }

    public function testRenderAcceptsANonOrderDocumentSource(): void
    {
        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode('de-DE');

        $lang = new LanguageEntity();
        $lang->setId(Uuid::randomHex());
        $lang->setLocale($locale);

        $source = new StaticDocumentSource(
            salesChannelId: Uuid::randomHex(),
            languageId: Uuid::randomHex(),
            language: $lang,
        );

        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects($this->once())
            ->method('injectSettings')
            ->with(
                $source->getSalesChannelId(),
                $source->getLanguageId(),
                $locale->getCode(),
            );

        $finder = static::createStub(TemplateFinder::class);
        $finder->method('find')->willReturn('path');

        $env = $this->createMock(TwigEnvironment::class);
        $env->expects($this->once())
            ->method('renderWithTimezoneOverride')
            ->with(
                'path',
                static::callback(static fn (array $parameters): bool => $parameters['order'] === $source),
                null,
            )
            ->willReturn('rendered');

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn(new SalesChannelEntity());

        $contextFactory = static::createStub(AbstractSalesChannelContextFactory::class);
        $contextFactory->method('create')->willReturn($salesChannelContext);

        $renderer = new DocumentTemplateRenderer(
            $finder,
            $env,
            $translator,
            $contextFactory,
            new EventDispatcher(),
            'rootDir',
        );

        $result = $renderer->render(
            'path',
            new RenderInput('quotes', '12345', $source),
            Context::createDefaultContext(),
        );

        static::assertSame('rendered', $result);
    }

    public function testRenderUsesSalesChannelBusinessTimeZone(): void
    {
        $twig = $this->createTwig('{{ testDate|format_date(pattern="yyyy-MM-dd", locale="en-GB") }}');

        $renderer = $this->createRenderer($twig, 'Europe/Berlin');

        $result = $renderer->render(
            'view',
            $this->createRenderInput(),
            Context::createDefaultContext(),
            ['testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC'))],
        );

        static::assertSame('2026-01-02', $result);
        static::assertSame('UTC', $twig->getExtension(CoreExtension::class)->getTimezone()->getName());
    }

    public function testRenderKeepsCurrentTimeZoneWithoutBusinessTimeZone(): void
    {
        $twig = $this->createTwig('{{ testDate|format_date(pattern="yyyy-MM-dd", locale="en-GB") }}');

        $renderer = $this->createRenderer($twig, null);

        $result = $renderer->render(
            'view',
            $this->createRenderInput(),
            Context::createDefaultContext(),
            ['testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC'))],
        );

        static::assertSame('2026-01-01', $result);
        static::assertSame('UTC', $twig->getExtension(CoreExtension::class)->getTimezone()->getName());
    }

    public function testRenderDispatchesTheV1ParameterEventWithTheTemplateScope(): void
    {
        $captured = null;
        $sequence = [];

        $translator = static::createStub(AbstractTranslator::class);
        $translator->method('injectSettings')
            ->willReturnCallback(function () use (&$sequence): void {
                $sequence[] = 'injectSettings';
            });

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            DocumentTemplateRendererParameterEvent::class,
            static function (DocumentTemplateRendererParameterEvent $event) use (&$captured, &$sequence): void {
                $captured = $event->getParameters();
                $sequence[] = 'listener';
            },
        );

        $renderer = $this->createRenderer($this->createTwig('rendered'), null, $eventDispatcher, $translator);
        $input = $this->createRenderInput();

        $renderer->render(
            'view',
            $input,
            Context::createDefaultContext(),
            ['pagination' => 'counter'],
        );

        static::assertIsArray($captured);
        static::assertSame($input->order, $captured['order']);
        static::assertSame('12345', $captured['documentNumber']);
        static::assertSame('rootDir', $captured['rootDir']);
        static::assertSame('counter', $captured['pagination']);
        static::assertInstanceOf(SalesChannelContext::class, $captured['context']);
        static::assertSame(['injectSettings', 'listener'], $sequence);
    }

    public function testRenderPassesExtensionsFromTheV1ParameterEventIntoTheTemplate(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            DocumentTemplateRendererParameterEvent::class,
            static function (DocumentTemplateRendererParameterEvent $event): void {
                $event->addExtension('badge', new ArrayStruct(['label' => 'paid via invoice']));
            },
        );

        $renderer = $this->createRenderer(
            $this->createTwig('{{ extensions.badge.get(\'label\') }}'),
            null,
            $eventDispatcher,
        );

        $result = $renderer->render(
            'view',
            $this->createRenderInput(),
            Context::createDefaultContext(),
        );

        static::assertSame('paid via invoice', $result);
    }

    public function testRenderPassesAnEmptyExtensionListWhenNobodySubscribes(): void
    {
        $renderer = $this->createRenderer($this->createTwig('{{ extensions|length }}'), null);

        $result = $renderer->render(
            'view',
            $this->createRenderInput(),
            Context::createDefaultContext(),
        );

        static::assertSame('0', $result);
    }

    private function createRenderer(
        TwigEnvironment $twig,
        ?string $businessTimeZone,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?AbstractTranslator $translator = null,
    ): DocumentTemplateRenderer {
        $templateFinder = static::createStub(TemplateFinder::class);
        $templateFinder->method('find')->willReturnArgument(0);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setBusinessTimeZone($businessTimeZone);

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        $contextFactory = static::createStub(AbstractSalesChannelContextFactory::class);
        $contextFactory->method('create')->willReturn($salesChannelContext);

        return new DocumentTemplateRenderer(
            $templateFinder,
            $twig,
            $translator ?? static::createStub(AbstractTranslator::class),
            $contextFactory,
            $eventDispatcher ?? new EventDispatcher(),
            'rootDir',
        );
    }

    private function createTwig(string $template): TwigEnvironment
    {
        $twig = new TwigEnvironment(new ArrayLoader([
            'view' => $template,
        ]));
        $twig->addExtension(new IntlExtension());

        /** @var CoreExtension $coreExtension */
        $coreExtension = $twig->getExtension(CoreExtension::class);
        $coreExtension->setTimezone('UTC');

        return $twig;
    }

    private function createRenderInput(): RenderInput
    {
        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode('en-GB');

        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $language->setLocale($locale);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId(Uuid::randomHex());
        $order->setLanguage($language);

        return new RenderInput(
            DocumentType::INVOICE->value,
            '12345',
            $order,
        );
    }
}
