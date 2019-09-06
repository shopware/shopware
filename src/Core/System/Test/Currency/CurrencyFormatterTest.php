<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Currency;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Currency\CurrencyFormatter;

class CurrencyFormatterTest extends TestCase
{
    use KernelTestBehaviour;
    use BasicTestDataBehaviour;

    public function testFormatByLanguage(): void
    {
        $currencyFormatter = $this->getContainer()->get(CurrencyFormatter::class);

        $price = (float) '132582.98765432';
        $context = Context::createDefaultContext();
        $deLanguageId = $this->getDeDeLanguageId();

        $formattedCurrency = $currencyFormatter->formatCurrencyByLanguage(
            $price,
            'EUR',
            $deLanguageId,
            $context
        );

        static::assertSame('132.582,99 €', $formattedCurrency);

        $formattedCurrency = $currencyFormatter->formatCurrencyByLanguage(
            $price,
            'EUR',
            Defaults::LANGUAGE_SYSTEM,
            $context
        );

        static::assertSame('€132,582.99', $formattedCurrency);

        $formattedCurrency = $currencyFormatter->formatCurrencyByLanguage(
            $price,
            'USD',
            $deLanguageId,
            $context
        );

        static::assertSame('132.582,99 $', $formattedCurrency);

        $formattedCurrency = $currencyFormatter->formatCurrencyByLanguage(
            $price,
            'USD',
            Defaults::LANGUAGE_SYSTEM,
            $context
        );

        static::assertSame('US$132,582.99', $formattedCurrency);
    }
}
