<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\NumberRange;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

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
        $this->connection = static::getContainer()->get(Connection::class);
        $this->setupDatabase();
        $this->context = Context::createDefaultContext();
    }

    public function testGetConfiguration(): void
    {
        /** @var NumberRangeValueGenerator $realGenerator */
        $realGenerator = static::getContainer()->get(NumberRangeValueGeneratorInterface::class);
        $value = $realGenerator->getValue('product', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('SW10000', $value);
        $value = $realGenerator->getValue('product', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('SW10001', $value);
        $value = $realGenerator->getValue('product', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('SW10002', $value);

        $value = $realGenerator->getValue('order', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('10000', $value);
        $value = $realGenerator->getValue('order', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('10001', $value);
        $value = $realGenerator->getValue('order', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('10002', $value);

        $value = $realGenerator->getValue('customer', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('10000', $value);
        $value = $realGenerator->getValue('customer', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('10001', $value);
        $value = $realGenerator->getValue('customer', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('10002', $value);
    }

    public function testIncreaseStartNumberInConfiguration(): void
    {
        /** @var NumberRangeValueGenerator $realGenerator */
        $realGenerator = static::getContainer()->get(NumberRangeValueGeneratorInterface::class);

        $value = $realGenerator->getValue('order', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('10000', $value);

        /** @var EntityRepository<NumberRangeTypeCollection> $numberRange */
        $numberRange = static::getContainer()->get('number_range_type.repository');
        $search = $numberRange->search((new Criteria())->addFilter(new EqualsFilter('technicalName', 'order')), $this->context)
            ->getEntities()
            ->first();

        static::assertNotNull($search);
        $typeId = $search->getId();

        /** @var EntityRepository<NumberRangeCollection> $numberRange */
        $numberRange = static::getContainer()->get('number_range.repository');

        $search = $numberRange->search((new Criteria())->addFilter(new EqualsFilter('typeId', $typeId)), $this->context)
            ->getEntities()
            ->first();

        static::assertNotNull($search);

        static::getContainer()->get('number_range.repository')->update([[
            'id' => $search->getId(),
            'start' => 20000,
        ]], $this->context);

        $value = $realGenerator->getValue('order', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('20000', $value);
    }

    public function testGetValueStartingFromZero(): void
    {
        /** @var NumberRangeValueGenerator $realGenerator */
        $realGenerator = static::getContainer()->get(NumberRangeValueGeneratorInterface::class);

        /** @var EntityRepository<NumberRangeCollection> $numberRange */
        $numberRange = static::getContainer()->get('number_range.repository');

        $search = $numberRange->search((new Criteria())->addFilter(new EqualsFilter('type.technicalName', 'order')), $this->context)
            ->getEntities()
            ->first();

        static::assertNotNull($search);

        static::getContainer()->get('number_range.repository')->update([[
            'id' => $search->getId(),
            'start' => 0,
        ]], $this->context);

        $value = $realGenerator->getValue('order', $this->context, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame('0', $value);
    }

    private function setupDatabase(): void
    {
        $sql = <<<'SQL'
            DELETE FROM `number_range_state`;
SQL;
        $this->connection->executeStatement($sql);
    }
}
