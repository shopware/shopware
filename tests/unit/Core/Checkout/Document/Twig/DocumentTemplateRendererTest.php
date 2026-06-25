<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Extension\CoreExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(DocumentTemplateRenderer::class)]
#[Package('after-sales')]
class DocumentTemplateRendererTest extends TestCase
{
    private static bool $rendererParameterEventCalled = false;

    public function testDocumentTemplateRendererParameterEventIsDispatched(): void
    {
        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder->expects($this->once())->method('reset');
        $templateFinder->expects($this->once())->method('find')->willReturnCallback(static function (string $template): string {
            static::assertTrue(self::$rendererParameterEventCalled, 'Expected DocumentTemplateRendererParameterEvent being thrown before TemplateFinder is called to ensure that the TemplateFinder is configured correctly');

            return $template;
        });

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(DocumentTemplateRendererParameterEvent::class))
            ->willReturnCallback(static function (DocumentTemplateRendererParameterEvent $event) {
                static::assertFalse(self::$rendererParameterEventCalled);
                self::$rendererParameterEventCalled = true;

                return $event;
            });

        $documentTemplateRenderer = new DocumentTemplateRenderer(
            $templateFinder,
            $this->createMock(TwigEnvironment::class),
            $this->createMock(Translator::class),
            $this->createMock(SalesChannelContextFactory::class),
            $eventDispatcher,
        );

        $salesChannelId = Uuid::randomHex();
        $documentTemplateRenderer->render('view', [], Context::createDefaultContext(), $salesChannelId, Uuid::randomHex(), 'en-GB');
    }

    public function testRenderUsesSalesChannelBusinessTimeZone(): void
    {
        $twig = $this->createTwig('{{ testDate|format_date(pattern="yyyy-MM-dd", locale="en-GB") }}');

        $renderer = $this->createRenderer($twig, 'Europe/Berlin');

        $result = $renderer->render(
            'view',
            ['testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC'))],
            Context::createDefaultContext(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            'en-GB'
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
            ['testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC'))],
            Context::createDefaultContext(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            'en-GB'
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

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        return new DocumentTemplateRenderer(
            $templateFinder,
            $twig,
            $this->createMock(AbstractTranslator::class),
            $contextFactory,
            $eventDispatcher,
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
}
