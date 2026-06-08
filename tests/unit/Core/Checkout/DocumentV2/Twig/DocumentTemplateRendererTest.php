<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
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
            ->willReturn(DocumentType::INVOICE->templatePath());

        $env = $this->createMock(TwigEnvironment::class);
        $env->expects($this->once())
            ->method('renderWithTimezoneOverride')
            ->with(
                DocumentType::INVOICE->templatePath(),
                static::callback(function (array $parameters) use ($order) {
                    return $parameters['order'] === $order
                        && $parameters['documentNumber'] === '12345'
                        && $parameters['rootDir'] === 'rootDir'
                        && !\array_key_exists('counter', $parameters)
                        && $parameters['context'] instanceof SalesChannelContext;
                }),
                null,
            )
            ->willReturn($template);

        $salesChannel = new SalesChannelEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory->method('create')->willReturn($salesChannelContext);

        $renderer = new DocumentTemplateRenderer(
            $finder,
            $env,
            $translator,
            $contextFactory,
            'rootDir',
        );

        $input = new RenderInput(
            DocumentType::INVOICE->value,
            '12345',
            $order,
        );

        $result = $renderer->render(
            DocumentType::INVOICE->templatePath(),
            $input,
            $context,
        );

        static::assertIsString($result);
        static::assertSame($template, $result);
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

    private function createRenderer(TwigEnvironment $twig, ?string $businessTimeZone): DocumentTemplateRenderer
    {
        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder->method('find')->willReturnArgument(0);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setBusinessTimeZone($businessTimeZone);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory->method('create')->willReturn($salesChannelContext);

        return new DocumentTemplateRenderer(
            $templateFinder,
            $twig,
            $this->createMock(AbstractTranslator::class),
            $contextFactory,
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
