<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Twig\Extension\CoreExtension;

/**
 * @internal
 */
class StringTemplateRendererTest extends TestCase
{
    use KernelTestBehaviour;

    private StringTemplateRenderer $stringTemplateRenderer;

    protected function setUp(): void
    {
        $this->stringTemplateRenderer = $this->getContainer()->get(StringTemplateRenderer::class);
    }

    public function testRender(): void
    {
        $templateMock = '{{ foo }}';
        $dataMock = ['foo' => 'bar'];
        $rendered = $this->stringTemplateRenderer->render($templateMock, $dataMock, Context::createDefaultContext());
        static::assertEquals('bar', $rendered);
    }

    public function testInitialization(): void
    {
        $templateMock = '{{ testDate|format_date(pattern="HH:mm") }}';
        $testDate = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $context = Context::createDefaultContext();

        /** @var CoreExtension $coreExtension */
        $coreExtension = $this->getContainer()->get('twig')->getExtension(CoreExtension::class);
        $coreExtension->setTimezone('Europe/London');
        $this->stringTemplateRenderer->initialize();
        $renderedTime = $this->stringTemplateRenderer->render($templateMock, ['testDate' => $testDate], $context);

        /** @var CoreExtension $coreExtension */
        $coreExtension = $this->getContainer()->get('twig')->getExtension(CoreExtension::class);
        $coreExtension->setTimezone('Europe/Berlin');
        $this->stringTemplateRenderer->initialize();

        $renderedWithTimezone = $this->stringTemplateRenderer->render($templateMock, ['testDate' => $testDate], $context);

        static::assertNotEquals($renderedTime, $renderedWithTimezone);
    }
}
