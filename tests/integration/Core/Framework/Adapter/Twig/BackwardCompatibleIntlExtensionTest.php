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

    private IntlExtension $intlExtension;

    protected function setUp(): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('This test is only relevant for versions before v6.8.0');
        }

        // Create a fresh Twig environment with IntlExtension and BackwardCompatibleIntlExtension
        $loader = new ArrayLoader();
        $this->twig = new Environment($loader);

        $this->intlExtension = new IntlExtension();
        $this->twig->addExtension($this->intlExtension);
        $this->twig->addExtension(new BackwardCompatibleIntlExtension($this->intlExtension));
    }

    public function testNumberFormatWithInvalidLocaleFallsBackToDefault(): void
    {
        $template = $this->twig->createTemplate('{{ value|format_number({fraction_digit: 1}, locale="zzz") }}');

        $output = $template->render(['value' => 1234567.891]);

        // Create expected value using IntlExtension with same settings as template (fraction_digit: 1)
        $expected = $this->intlExtension->formatNumber(1234567.891, ['fraction_digit' => 1], 'decimal', 'default');
        static::assertSame($expected, $output);
    }

    public function testCurrencyFormatWithInvalidLocaleFallsBackToDefault(): void
    {
        $template = $this->twig->createTemplate('{{ value|format_currency("USD", locale="zzz") }}');

        $output = $template->render(['value' => 1234567.891]);

        // Create expected value using IntlExtension with same settings as template
        $expected = $this->intlExtension->formatCurrency(1234567.891, 'USD', []);
        static::assertSame($expected, $output);
    }
}
