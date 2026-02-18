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

    private Environment $twig;

    protected function setUp(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('This test is only relevant for versions before v6.8.0');
        }

        // Create a fresh Twig environment with IntlExtension and BackwardCompatibleIntlExtension
        $loader = new ArrayLoader();
        $this->twig = new Environment($loader);

        $intlExtension = new IntlExtension();
        $this->twig->addExtension($intlExtension);
        $this->twig->addExtension(new BackwardCompatibleIntlExtension($intlExtension));
    }

    public function testNumberFormatWithInvalidLocaleFallsBackToDefault(): void
    {
        $template = $this->twig->createTemplate('{{ value|format_number({fraction_digit: 1}, locale="zzz") }}');

        $output = $template->render(['value' => 1234567.891]);

        // Create formatter with same settings as the template (fraction_digit: 1)
        $formatter = \NumberFormatter::create(\Locale::getDefault(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 1);
        $expected = $formatter->format(1234567.891);
        static::assertSame($expected, $output);
    }

    public function testCurrencyFormatWithInvalidLocaleFallsBackToDefault(): void
    {
        $template = $this->twig->createTemplate('{{ value|format_currency("USD", locale="zzz") }}');

        $output = $template->render(['value' => 1234567.891]);

        // Currency format uses 2 decimal places by default
        $expected = \NumberFormatter::create(\Locale::getDefault(), \NumberFormatter::CURRENCY)->formatCurrency(1234567.891, 'USD');
        static::assertSame($expected, $output);
    }
}
