<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\NumberRange;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\NumberRange\NumberRangeEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternDate;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternIncrement;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry;

class NumberRangeValueGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /** @var Context */
    private $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = Context::createDefaultContext();
    }

    public function testGenerateStandardPattern(): void
    {
        $generator = $this->getGenerator('Pre_{n}_suf');
        $value = $generator->getValue(ProductDefinition::class, $this->context, null);
        static::assertEquals('Pre_5_suf', $value);
    }

    public function testGenerateDatePattern(): void
    {
        $generator = $this->getGenerator('Pre_{date}_suf');
        $value = $generator->getValue(ProductDefinition::class, $this->context, null);
        static::assertEquals('Pre_' . date(ValueGeneratorPatternDate::STANDARD_FORMAT) . '_suf', $value);
    }

    public function testGenerateDateWithFormatPattern(): void
    {
        $generator = $this->getGenerator('Pre_{date_ymd}_suf');
        $value = $generator->getValue(ProductDefinition::class, $this->context, null);
        static::assertEquals('Pre_' . date('ymd') . '_suf', $value);
    }

    public function testGenerateAllPatterns(): void
    {
        $generator = $this->getGenerator('Pre_{date}_{date_ymd}_{n}_suf');
        $value = $generator->getValue(ProductDefinition::class, $this->context, null);
        static::assertEquals(
            'Pre_' . date(ValueGeneratorPatternDate::STANDARD_FORMAT) . '_' . date('ymd') . '_5_suf', $value
        );
    }

    public function testGenerateExtraCharsAllPatterns(): void
    {
        $generator = $this->getGenerator('Pre_!"§$%&/()=_{date}_{date_ymd}_{n}_suf');
        $value = $generator->getValue(ProductDefinition::class, $this->context, null);
        static::assertEquals('Pre_!"§$%&/()=_' . date(ValueGeneratorPatternDate::STANDARD_FORMAT) . '_' . date('ymd') . '_5_suf', $value);
    }

    private function getGenerator($pattern): NumberRangeValueGenerator
    {
        $patternReg = $this->getContainer()->get(ValueGeneratorPatternRegistry::class);

        $patternRegMock = $this->createMock(ValueGeneratorPatternRegistry::class);
        $incrPattern = $this->createMock(ValueGeneratorPatternIncrement::class);
        $incrPattern->expects(static::any())->method('resolve')->willReturn(5);

        $patternRegMock->expects(static::any())->method('getPatternResolver')->will(
            static::returnCallback(function ($arg) use ($incrPattern, $patternReg) {
                if ($arg === 'n') {
                    return $incrPattern;
                }

                return $patternReg->getPatternResolver($arg);
            })
        );

        $configuration = new NumberRangeEntity();
        $configuration->setUniqueIdentifier('asdasdsad');
        $configuration->setPattern($pattern);
        $configuration->setStart(1);

        $entityReader = $this->createMock(EntityReaderInterface::class);
        $entityReader->expects(static::once())->method('read')->willReturn(
            new EntityCollection([$configuration])
        );

        $generator = new NumberRangeValueGenerator(
            $patternRegMock,
            $entityReader,
            $this->getContainer()->get('event_dispatcher')
        );

        return $generator;
    }
}
