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
        // Create a fresh Twig environment with IntlExtension and BackwardCompatibleIntlExtension
        $loader = new ArrayLoader();
        $twig = new Environment($loader);

        $intlExtension = new IntlExtension();
        $twig->addExtension($intlExtension);
        $twig->addExtension(new BackwardCompatibleIntlExtension($intlExtension));

        $template = $twig->createTemplate('{{ value|format_number({fraction_digit: 1}, locale="zz") }}');

        $output = $template->render(['value' => 1234567.891]);

        // Create expected value using IntlExtension with same settings as template (fraction_digit: 1)
        $expected = $intlExtension->formatNumber(1234567.891, ['fraction_digit' => 1]);
        static::assertSame($expected, $output);
    }

    public function testCurrencyFormatWithInvalidLocaleFallsBackToDefault(): void
    {
        // Create a fresh Twig environment with IntlExtension and BackwardCompatibleIntlExtension
        $loader = new ArrayLoader();
        $twig = new Environment($loader);

        $intlExtension = new IntlExtension();
        $twig->addExtension($intlExtension);
        $twig->addExtension(new BackwardCompatibleIntlExtension($intlExtension));

        $template = $twig->createTemplate('{{ value|format_currency("USD", locale="zzz") }}');

        $output = $template->render(['value' => 1234567.891]);

        // Create expected value using IntlExtension with same settings as template
        $expected = $intlExtension->formatCurrency(1234567.891, 'USD');
        static::assertSame($expected, $output);
    }
}
