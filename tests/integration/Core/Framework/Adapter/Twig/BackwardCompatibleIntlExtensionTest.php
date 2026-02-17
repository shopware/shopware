<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\BackwardCompatibleIntlExtension;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Twig\Environment;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed, because we don't support invalid locales anymore
 */
class BackwardCompatibleIntlExtensionTest extends TestCase
{
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('This test is only relevant for versions before v6.8.0');
        }
    }

    public function testNumberFormatWithInvalidLocaleFallsBackToDefault(): void
    {
        $twig = $this->createTwigEnvironment();

        $template = $twig->createTemplate('{{ value|format_number({fraction_digit: 1}, locale="us") }}');

        $output = $template->render(['value' => 1234567.891]);

        static::assertSame('1234567.9', $output);
    }

    public function testCurrencyFormatWithInvalidLocaleFallsBackToDefault(): void
    {
        $twig = $this->createTwigEnvironment();

        $template = $twig->createTemplate('{{ value|format_currency("USD", locale="us") }}');

        $output = $template->render(['value' => 1234567.891]);

        static::assertSame("\$\u{a0}1234567.89", $output);
    }

    private function createTwigEnvironment(): Environment
    {
        $loader = new ArrayLoader();
        $twig = new Environment($loader);

        // Add IntlExtension and BackwardCompatibleIntlExtension
        $intlExtension = new IntlExtension();
        $twig->addExtension($intlExtension);
        $twig->addExtension(new BackwardCompatibleIntlExtension($intlExtension));

        return $twig;
    }
}
