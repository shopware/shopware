<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MeasurementSystem\TwigExtension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\TwigExtension\MeasurementConvertUnitTwigFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class MeasurementConvertUnitTwigFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MeasurementConvertUnitTwigFilter $measurementConvertUnitTwigFilter;

    protected function setUp(): void
    {
        $this->measurementConvertUnitTwigFilter = static::getContainer()->get(MeasurementConvertUnitTwigFilter::class);
    }

    public function testTwigFilterIsRegistered(): void
    {
        $filters = $this->measurementConvertUnitTwigFilter->getFilters();

        static::assertCount(1, $filters);
        static::assertSame('sw_convert_unit', $filters[0]->getName());
    }

    public function testConvertWithBasicLengthConversion(): void
    {
        $twigContext = [];

        $result = $this->measurementConvertUnitTwigFilter->convert($twigContext, 1000, 'mm', 'm');

        static::assertSame('1 m', $result);
    }

    public function testConvertWithDecimalValues(): void
    {
        $twigContext = [];

        $result = $this->measurementConvertUnitTwigFilter->convert($twigContext, 150.5, 'cm', 'm');

        static::assertSame('1.51 m', $result);
    }

    public function testConvertWithStringNumericValue(): void
    {
        $twigContext = [];

        $result = $this->measurementConvertUnitTwigFilter->convert($twigContext, '500', 'mm', 'cm');

        static::assertSame('50 cm', $result);
    }

    public function testConvertWithNonNumericValue(): void
    {
        $twigContext = [];

        $result = $this->measurementConvertUnitTwigFilter->convert($twigContext, 'invalid', 'mm', 'cm');

        static::assertSame('invalid', $result);
    }

    public function testConvertWithNullValue(): void
    {
        $twigContext = [];

        $result = $this->measurementConvertUnitTwigFilter->convert($twigContext, null, 'mm', 'cm');

        static::assertNull($result);
    }

    public function testConvertWithNoToUnitFallsBackToFromUnit(): void
    {
        $twigContext = [];

        $result = $this->measurementConvertUnitTwigFilter->convert($twigContext, 100, 'mm', null);

        static::assertSame('100 mm', $result);
    }

    public function testConvertWithSalesChannelContext(): void
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create('', TestDefaults::SALES_CHANNEL);

        $twigContext = ['context' => $salesChannelContext];

        // Test conversion where the 'to' unit is automatically determined from sales channel context
        $result = $this->measurementConvertUnitTwigFilter->convert($twigContext, 1000, 'mm');

        static::assertIsString($result);
        static::assertStringContainsString('mm', $result);
    }

    public function testConvertWithPrecision(): void
    {
        $twigContext = [];

        $result = $this->measurementConvertUnitTwigFilter->convert($twigContext, 1000, 'mm', 'm', 2);

        static::assertSame('1 m', $result);
    }

    public function testConvertWithZeroValue(): void
    {
        $twigContext = [];

        $result = $this->measurementConvertUnitTwigFilter->convert($twigContext, 0, 'mm', 'cm');

        static::assertSame('0 cm', $result);
    }

    public function testConvertWithWeightUnits(): void
    {
        $twigContext = [];

        $result = $this->measurementConvertUnitTwigFilter->convert($twigContext, 1000, 'g', 'kg');

        static::assertSame('1 kg', $result);
    }

    public function testConvertWithImperialUnits(): void
    {
        $twigContext = [];

        $result = $this->measurementConvertUnitTwigFilter->convert($twigContext, 12, 'in', 'ft');

        static::assertSame('1 ft', $result);
    }
}
