<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
class StringTemplateRendererTest extends TestCase
{
    use KernelTestBehaviour;

    private StringTemplateRenderer $stringTemplateRenderer;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->stringTemplateRenderer = static::getContainer()->get(StringTemplateRenderer::class);
        $this->cacheDir = sys_get_temp_dir() . '/' . uniqid('shopware-string-template-renderer', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDir);
    }

    public function testRender(): void
    {
        $templateMock = '{{ foo }}';
        $dataMock = ['foo' => 'bar'];
        $rendered = $this->stringTemplateRenderer->render($templateMock, $dataMock, Context::createDefaultContext());
        static::assertSame('bar', $rendered);
    }

    public function testInitialization(): void
    {
        $templateMock = '{{ testDate|format_date(pattern="HH:mm") }}';
        $testDate = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $context = Context::createDefaultContext();

        $coreExtension = static::getContainer()->get('twig')->getExtension(CoreExtension::class);
        static::assertInstanceOf(CoreExtension::class, $coreExtension);
        $coreExtension->setTimezone('Europe/London');
        $this->stringTemplateRenderer->initialize();
        $renderedTime = $this->stringTemplateRenderer->render($templateMock, ['testDate' => $testDate], $context);

        $coreExtension = static::getContainer()->get('twig')->getExtension(CoreExtension::class);
        static::assertInstanceOf(CoreExtension::class, $coreExtension);
        $coreExtension->setTimezone('Europe/Berlin');
        $this->stringTemplateRenderer->initialize();

        $renderedWithTimezone = $this->stringTemplateRenderer->render($templateMock, ['testDate' => $testDate], $context);

        static::assertNotSame($renderedTime, $renderedWithTimezone);
    }

    public function testRenderWithTimezone(): void
    {
        $templateMock = '{{ testDate|format_date(pattern="HH:mm") }}';
        $testDate = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $coreExtension = static::getContainer()->get('twig')->getExtension(CoreExtension::class);
        static::assertInstanceOf(CoreExtension::class, $coreExtension);
        $coreExtension->setTimezone('Europe/London');
        $this->stringTemplateRenderer->initialize();

        $renderedWithoutTimezone = $this->stringTemplateRenderer->render(
            $templateMock,
            ['testDate' => $testDate],
            Context::createDefaultContext()
        );

        $renderedWithTimezone = $this->stringTemplateRenderer->render(
            $templateMock,
            ['testDate' => $testDate, 'timezone' => 'Europe/Berlin'],
            Context::createDefaultContext()
        );

        static::assertNotSame($renderedWithoutTimezone, $renderedWithTimezone);
    }

    #[DataProvider('labelRenderingDataProvider')]
    public function testTranslationRendering(string $label, string $expected): void
    {
        $template = <<<'TWIG'
{% set label = item.label|trans({}, 'storefront') %}
{{ label }}
TWIG;

        $context = Context::createDefaultContext();

        $item = new LineItem('test', 'test');
        $item->setLabel($label);

        $environment = new Environment(new ArrayLoader());
        $translator = static::createStub(Translator::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static function (string $id) {
                if ($id === 'general.homeLink') {
                    return 'Home';
                }

                return $id;
            });

        $environment->addExtension(new TranslationExtension($translator));

        $renderer = new StringTemplateRenderer($environment, $this->cacheDir);
        $result = $renderer->render($template, ['item' => $item], $context);

        static::assertSame($expected, $result);
    }

    public static function labelRenderingDataProvider(): \Generator
    {
        yield 'Test normal label' => ['Some cool product name', 'Some cool product name'];
        yield 'Test with special chars' => ['Some cool ! % & product name', 'Some cool ! % &amp; product name'];
        yield 'Test existing snippet' => ['general.homeLink', 'Home'];
        yield 'Test none existing snippet' => ['general.homeLink-foo', 'general.homeLink-foo'];
    }

    public function testRenderThrowsAdapterExceptionDueInvalidSyntax(): void
    {
        $template = <<<'TWIG'
{{ label }
TWIG;

        $context = Context::createDefaultContext();
        $environment = new Environment(new ArrayLoader());

        $renderer = new StringTemplateRenderer($environment, $this->cacheDir);

        $this->expectException(AdapterException::class);
        // Twig 3.28 appends the column to syntax error messages, older versions stop at the line
        $this->expectExceptionMessageMatches('/Failed rendering Twig string template due syntax error: "Unexpected "}" in "[^"]+" at line 1( column \d+)?."/');
        $renderer->render($template, [], $context);
    }
}
