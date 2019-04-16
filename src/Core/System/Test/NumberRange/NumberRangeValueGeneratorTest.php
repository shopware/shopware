<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\NumberRange;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\NumberRange\NumberRangeEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternDate;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternIncrement;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry;

class NumberRangeValueGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /** @var Context */
    private $context;

    /** @var Connection */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->setUpDatabase();
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

    public function testGetConfiguration(): void
    {
        /** @var NumberRangeValueGenerator $realGenerator */
        $realGenerator = $this->getContainer()->get(NumberRangeValueGeneratorInterface::class);
        $value = $realGenerator->getValue('product', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('SW10000', $value);
        $value = $realGenerator->getValue('product', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('SW10001', $value);
        $value = $realGenerator->getValue('product', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('SW10002', $value);

        $value = $realGenerator->getValue('order', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('10000', $value);
        $value = $realGenerator->getValue('order', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('10001', $value);
        $value = $realGenerator->getValue('order', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('10002', $value);

        $value = $realGenerator->getValue('customer', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('10000', $value);
        $value = $realGenerator->getValue('customer', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('10001', $value);
        $value = $realGenerator->getValue('customer', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('10002', $value);
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

    private function setupDatabase()
    {
        $sql = <<<SQL
            DELETE FROM `number_range_state`;
SQL;
        $this->connection->executeQuery($sql);
    }
}
