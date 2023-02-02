<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\NumberRange;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternDate;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternIncrement;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry;

/**
 * @internal
 */
class NumberRangeValueGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->setupDatabase();
        $this->context = Context::createDefaultContext();
    }

    public function testGenerateStandardPattern(): void
    {
        $value = $this->getGenerator('Pre_{n}_suf')->getValue(ProductDefinition::class, $this->context, null);
        static::assertEquals('Pre_5_suf', $value);
    }

    public function testGenerateDatePattern(): void
    {
        $value = $this->getGenerator('Pre_{date}_suf')->getValue(ProductDefinition::class, $this->context, null);
        static::assertEquals('Pre_' . date(ValueGeneratorPatternDate::STANDARD_FORMAT) . '_suf', $value);
    }

    public function testGenerateDateWithFormatPattern(): void
    {
        $value = $this->getGenerator('Pre_{date_ymd}_suf')->getValue(ProductDefinition::class, $this->context, null);
        static::assertEquals('Pre_' . date('ymd') . '_suf', $value);
    }

    public function testGenerateAllPatterns(): void
    {
        $value = $this->getGenerator('Pre_{date}_{date_ymd}_{n}_suf')->getValue(ProductDefinition::class, $this->context, null);
        static::assertEquals(
            'Pre_' . date(ValueGeneratorPatternDate::STANDARD_FORMAT) . '_' . date('ymd') . '_5_suf',
            $value
        );
    }

    public function testGenerateExtraCharsAllPatterns(): void
    {
        $value = $this->getGenerator('Pre_!"§$%&/()=_{date}_{date_ymd}_{n}_suf')->getValue(ProductDefinition::class, $this->context, null);
        static::assertEquals(
            'Pre_!"§$%&/()=_' . date(ValueGeneratorPatternDate::STANDARD_FORMAT) . '_' . date('ymd') . '_5_suf',
            $value
        );
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

    public function testIncreaseStartNumberInConfiguration(): void
    {
        /** @var NumberRangeValueGenerator $realGenerator */
        $realGenerator = $this->getContainer()->get(NumberRangeValueGeneratorInterface::class);

        $value = $realGenerator->getValue('order', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('10000', $value);

        $typeId = $this->getContainer()->get('number_range_type.repository')
            ->search((new Criteria())->addFilter(new EqualsFilter('technicalName', 'order')), $this->context)
            ->first()
            ->getId();
        $numberRange = $this->getContainer()->get('number_range.repository')
            ->search((new Criteria())->addFilter(new EqualsFilter('typeId', $typeId)), $this->context)
            ->first();

        $this->getContainer()->get('number_range.repository')->update([[
            'id' => $numberRange->getId(),
            'start' => 20000,
        ]], $this->context);

        $value = $realGenerator->getValue('order', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertEquals('20000', $value);
    }

    private function getGenerator(string $pattern): NumberRangeValueGenerator
    {
        $incrPattern = $this->createMock(ValueGeneratorPatternIncrement::class);
        $incrPattern->method('getPatternId')->willReturn('n');
        $incrPattern->method('generate')->willReturn('5');

        $patternReg = new ValueGeneratorPatternRegistry([$incrPattern, new ValueGeneratorPatternDate()]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchAssociative')
            ->willReturn(['id' => Uuid::randomHex(), 'pattern' => $pattern, 'start' => 1]);

        return new NumberRangeValueGenerator(
            $patternReg,
            $this->getContainer()->get('event_dispatcher'),
            $connection,
        );
    }

    private function setupDatabase(): void
    {
        $sql = <<<'SQL'
            DELETE FROM `number_range_state`;
SQL;
        $this->connection->executeStatement($sql);
    }
}
