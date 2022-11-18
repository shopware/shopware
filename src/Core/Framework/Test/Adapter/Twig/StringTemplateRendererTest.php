<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * @internal
 */
class StringTemplateRendererTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var StringTemplateRenderer
     */
    private $stringTemplateRenderer;

    /**
     * @var Environment
     */
    private $twig;

    protected function setUp(): void
    {
        $this->stringTemplateRenderer = $this->getContainer()->get(StringTemplateRenderer::class);
        $this->twig = $this->getContainer()->get('twig');
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
        $renderedTime = $this->stringTemplateRenderer->render($templateMock, ['testDate' => $testDate], $context);

        /** @var CoreExtension $coreExtension */
        $coreExtension = $this->twig->getExtension(CoreExtension::class);
        $coreExtension->setTimezone('Europe/Berlin');
        $this->stringTemplateRenderer->initialize();

        $renderedWithTimezone = $this->stringTemplateRenderer->render($templateMock, ['testDate' => $testDate], $context);

        static::assertNotEquals($renderedTime, $renderedWithTimezone);
    }
}
