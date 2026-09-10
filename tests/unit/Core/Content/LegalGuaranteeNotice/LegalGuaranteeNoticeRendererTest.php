<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\LegalGuaranteeNotice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(LegalGuaranteeNoticeRenderer::class)]
class LegalGuaranteeNoticeRendererTest extends TestCase
{
    private const RESOURCE_DIR = __DIR__ . '/../../../../../src/Core/Content/Resources/views/legal-guarantee-notice';

    public function testRenderForLanguageUsesResolvedLocale(): void
    {
        $renderer = $this->createRenderer('de');

        $result = $renderer->renderForLanguage('irrelevant-language-id');

        static::assertSame($this->readFixture('de'), $result);
    }

    public function testRenderForLanguageFallsBackToEnglishForUnsupportedLocale(): void
    {
        $renderer = $this->createRenderer('tr');

        $result = $renderer->renderForLanguage('irrelevant-language-id');

        static::assertSame($this->readFixture('en'), $result);
    }

    public function testLinkForLanguageResolvesGermanLink(): void
    {
        $renderer = $this->createRenderer('de');

        static::assertSame('https://europa.eu/youreurope/garantien', $renderer->linkForLanguage('irrelevant-language-id'));
    }

    #[TestWith(['bg', 'https://europa.eu/youreurope/%D0%B3%D0%B0%D1%80%D0%B0%D0%BD%D1%86%D0%B8%D0%B8'])]
    #[TestWith(['el', 'https://europa.eu/youreurope/%CE%B5%CE%B3%CE%B3%CF%85%CE%AE%CF%83%CE%B5%CE%B9%CF%82'])]
    #[TestWith(['sv', 'https://europa.eu/youreurope/reklamationsr%C3%A4tt'])]
    public function testLinkForLanguageUrlEncodesNonAsciiSlugs(string $locale, string $expectedLink): void
    {
        $renderer = $this->createRenderer($locale);

        static::assertSame($expectedLink, $renderer->linkForLanguage('irrelevant-language-id'));
    }

    public function testLinkForLanguageFallsBackToEnglishForUnsupportedLocale(): void
    {
        $renderer = $this->createRenderer('xx');

        static::assertSame('https://europa.eu/youreurope/guarantees', $renderer->linkForLanguage('irrelevant-language-id'));
    }

    private function createRenderer(string $localePrefix): LegalGuaranteeNoticeRenderer
    {
        $twig = new Environment(new ArrayLoader([
            '@Content/legal-guarantee-notice/de.svg' => $this->readFixture('de'),
            '@Content/legal-guarantee-notice/en.svg' => $this->readFixture('en'),
        ]));

        $localeCodeProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $localeCodeProvider->method('getLanguageLocalePrefix')->willReturn($localePrefix);

        return new LegalGuaranteeNoticeRenderer($twig, $localeCodeProvider);
    }

    private function readFixture(string $locale): string
    {
        $content = file_get_contents(self::RESOURCE_DIR . '/' . $locale . '.svg');
        static::assertIsString($content);

        return $content;
    }
}
