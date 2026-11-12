<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class ContentTextComponentTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testMappedTextIsPurifiedByTheStorefrontTextComponent(): void
    {
        $mappedValue = '<p>Alpha & <strong>mapped</strong></p><script>alert(1)</script>';
        $escapedMappedValue = htmlspecialchars($mappedValue, \ENT_QUOTES, 'UTF-8');

        $html = $this->renderText($escapedMappedValue);

        static::assertStringContainsString('<p>Alpha &amp; <strong>mapped</strong></p>', $html);
        static::assertStringNotContainsString('<script', $html);
        static::assertStringNotContainsString('&amp;amp;', $html);
    }

    private function renderText(string $text): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig
            ->createTemplate('{{ component(\'Sw:Content:Text\', { text: text }) }}')
            ->render(['text' => $text]);
    }
}
