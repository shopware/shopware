<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\Filter\CurrencyFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\Test\Generator;
use Twig\TwigFilter;

/**
 * @internal
 */
#[CoversClass(CurrencyFilter::class)]
class CurrencyFilterTest extends TestCase
{
    public function testGetFilters(): void
    {
        $filter = new CurrencyFilter($this->createMock(CurrencyFormatter::class));

        $filters = $filter->getFilters();

        static::assertCount(1, $filters);
        static::assertInstanceOf(TwigFilter::class, $filters[0]);
        static::assertSame('currency', $filters[0]->getName());
    }

    public function testFormatsCurrencyWithCoreContextAndExplicitCurrencyIsoCode(): void
    {
        $context = Context::createDefaultContext();
        $languageId = Uuid::randomHex();

        $formatter = $this->createMock(CurrencyFormatter::class);
        $formatter
            ->expects($this->once())
            ->method('formatCurrencyByLanguage')
            ->with(12.34, 'USD', $languageId, $context, 3)
            ->willReturn('$12.340');

        $filter = new CurrencyFilter($formatter);

        static::assertSame('$12.340', $filter->formatCurrency([
            'context' => $context,
        ], 12.34, 'USD', $languageId, 3));
    }

    public function testFormatsNullPriceAsZero(): void
    {
        $context = Context::createDefaultContext();

        $formatter = $this->createMock(CurrencyFormatter::class);
        $formatter
            ->expects($this->once())
            ->method('formatCurrencyByLanguage')
            ->with(0.0, 'USD', $context->getLanguageId(), $context, 2)
            ->willReturn('$0.00');

        $filter = new CurrencyFilter($formatter);

        static::assertSame('$0.00', $filter->formatCurrency([
            'context' => $context,
        ], null, 'USD', null, 2));
    }

    public function testCurrencyIsoCodeFallsBackToSalesChannelContextInContextVariable(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext(
            currency: $this->createCurrency('CHF'),
        );
        $context = $salesChannelContext->getContext();

        $formatter = $this->createMock(CurrencyFormatter::class);
        $formatter
            ->expects($this->once())
            ->method('formatCurrencyByLanguage')
            ->with(12.34, 'CHF', $context->getLanguageId(), $context, null)
            ->willReturn('CHF 12.34');

        $filter = new CurrencyFilter($formatter);

        static::assertSame('CHF 12.34', $filter->formatCurrency([
            'context' => $salesChannelContext,
        ], 12.34));
    }

    public function testCurrencyIsoCodeFallsBackToSalesChannelContext(): void
    {
        $context = Context::createDefaultContext();

        $salesChannelContext = Generator::generateSalesChannelContext(
            baseContext: $context,
            currency: $this->createCurrency('EUR'),
        );

        $formatter = $this->createMock(CurrencyFormatter::class);
        $formatter
            ->expects($this->once())
            ->method('formatCurrencyByLanguage')
            ->with(12.34, 'EUR', $context->getLanguageId(), $context, null)
            ->willReturn('EUR 12.34');

        $filter = new CurrencyFilter($formatter);

        static::assertSame('EUR 12.34', $filter->formatCurrency([
            'context' => $context,
            'salesChannelContext' => $salesChannelContext,
        ], 12.34));
    }

    public function testReturnsPriceInTestModeWhenContextIsMissing(): void
    {
        $filter = new CurrencyFilter($this->createMock(CurrencyFormatter::class));

        static::assertSame(12.34, $filter->formatCurrency([
            'testMode' => true,
        ], 12.34));
    }

    public function testThrowsWhenContextIsMissing(): void
    {
        $filter = new CurrencyFilter($this->createMock(CurrencyFormatter::class));

        $this->expectExceptionObject(AdapterException::currencyFilterMissingContext());

        $filter->formatCurrency([], 12.34);
    }

    public function testReturnsPriceInTestModeWhenCurrencyIsoCodeIsMissing(): void
    {
        $filter = new CurrencyFilter($this->createMock(CurrencyFormatter::class));

        static::assertSame(12.34, $filter->formatCurrency([
            'context' => Context::createDefaultContext(),
            'testMode' => true,
        ], 12.34));
    }

    public function testThrowsWhenCurrencyIsoCodeIsMissing(): void
    {
        $filter = new CurrencyFilter($this->createMock(CurrencyFormatter::class));

        $this->expectExceptionObject(AdapterException::currencyFilterMissingIsoCode());

        $filter->formatCurrency([
            'context' => Context::createDefaultContext(),
        ], 12.34);
    }

    private function createCurrency(string $isoCode): CurrencyEntity
    {
        $context = Context::createDefaultContext();

        $currency = new CurrencyEntity();
        $currency->setId($context->getCurrencyId());
        $currency->setFactor($context->getCurrencyFactor());
        $currency->setIsoCode($isoCode);

        return $currency;
    }
}
