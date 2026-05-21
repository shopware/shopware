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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Environment;

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

        $env = $this->createMock(Environment::class);
        $env->expects($this->once())
            ->method('render')
            ->with(
                DocumentType::INVOICE->templatePath(),
                static::callback(function (array $parameters) use ($order) {
                    return $parameters['order'] === $order
                        && $parameters['documentNumber'] === '12345'
                        && $parameters['rootDir'] === 'rootDir'
                        && !\array_key_exists('counter', $parameters)
                        && $parameters['context'] instanceof SalesChannelContext;
                })
            )
            ->willReturn($template);

        $renderer = new DocumentTemplateRenderer(
            $finder,
            $env,
            $translator,
            $this->createMock(AbstractSalesChannelContextFactory::class),
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
}
