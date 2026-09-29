<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\PriceModifier\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationCollection;
use Shopware\Core\Checkout\PriceModifier\Order\OrderPriceModificationTaxLockValidator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * Reproduces the real bug the unit test suite (mocked Connection) cannot: a native MySQL `JSON`
 * column reformats whatever gets stored (adds whitespace, doesn't guarantee key order), so a value
 * read back via a real SELECT is never byte-identical to a fresh json_encode() of the same semantic
 * value. OrderPriceModificationTaxLockValidator used to compare those two representations with a
 * raw `!==`, which would have rejected every one of these as a "change" even though nothing actually
 * changed. This test only passes because the comparison is now semantic (decode-and-compare), not
 * because the values happen to match textually.
 *
 * @internal
 */
#[Package('checkout')]
class OrderPriceModificationTaxLockValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<OrderPriceModificationCollection>
     */
    private EntityRepository $priceModificationRepository;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->priceModificationRepository = static::getContainer()->get('order_price_modification.repository');
    }

    public function testMysqlReformatsStoredJsonDifferentlyFromAFreshEncode(): void
    {
        $orderId = $this->createOrder();
        $modificationId = Uuid::randomHex();

        $this->priceModificationRepository->create([[
            'id' => $modificationId,
            'orderId' => $orderId,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'label' => 'System voucher',
            'price' => -10.0,
            'type' => 'test_plugin',
            'taxRules' => [['taxRate' => 19.0, 'percentage' => 100.0]],
        ]], Context::createDefaultContext());

        $stored = $this->connection->fetchOne(
            'SELECT tax_rules FROM order_price_modification WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($modificationId)]
        );

        // Sanity check that the scenario this test guards against is real in this environment: MySQL
        // added whitespace that a compact json_encode() of the same value never would.
        static::assertIsString($stored);
        static::assertStringContainsString(': ', $stored);
    }

    public function testResendingTheSameTaxRulesOnALockedRowIsNotRejected(): void
    {
        $orderId = $this->createOrder();
        $modificationId = Uuid::randomHex();

        $this->priceModificationRepository->create([[
            'id' => $modificationId,
            'orderId' => $orderId,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'label' => 'System voucher',
            'price' => -10.0,
            'type' => 'test_plugin',
            'taxRules' => [['taxRate' => 19.0, 'percentage' => 100.0]],
        ]], Context::createDefaultContext());

        $exceptionWasThrown = false;
        try {
            // Resends the same semantic tax_rules value alongside an unrelated field change --
            // exactly what OrderPriceModificationProcessor::toPriceModifier() does on every
            // system-side rewrite of a type-tagged row (see the class docblock).
            $this->priceModificationRepository->update([[
                'id' => $modificationId,
                'label' => 'System voucher (renamed)',
                'taxRules' => [['taxRate' => 19.0, 'percentage' => 100.0]],
            ]], Context::createDefaultContext());
        } catch (WriteException) {
            $exceptionWasThrown = true;
        }

        static::assertFalse(
            $exceptionWasThrown,
            'Resending an unchanged tax_rules value must not trip ' . OrderPriceModificationTaxLockValidator::class
        );

        $modification = $this->priceModificationRepository
            ->search(new Criteria([$modificationId]), Context::createDefaultContext())
            ->getEntities()
            ->get($modificationId);

        static::assertNotNull($modification);
        static::assertSame('System voucher (renamed)', $modification->getLabel());
    }

    public function testChangingTheTaxRulesOnALockedRowIsStillRejected(): void
    {
        $orderId = $this->createOrder();
        $modificationId = Uuid::randomHex();

        $this->priceModificationRepository->create([[
            'id' => $modificationId,
            'orderId' => $orderId,
            'orderVersionId' => Defaults::LIVE_VERSION,
            'label' => 'System voucher',
            'price' => -10.0,
            'type' => 'test_plugin',
            'taxRules' => [['taxRate' => 19.0, 'percentage' => 100.0]],
        ]], Context::createDefaultContext());

        $exceptionWasThrown = false;
        try {
            $this->priceModificationRepository->update([[
                'id' => $modificationId,
                'taxRules' => [['taxRate' => 7.0, 'percentage' => 100.0]],
            ]], Context::createDefaultContext());
        } catch (WriteException) {
            $exceptionWasThrown = true;
        }

        static::assertTrue(
            $exceptionWasThrown,
            'A genuine tax_rules change on a system-contributed row must still be rejected'
        );
    }

    private function createOrder(): string
    {
        $orderId = Uuid::randomHex();

        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO `order` SET
                id = :orderId,
                version_id = :defaultVersion,
                state_id = (SELECT `initial_state_id` FROM `state_machine` WHERE `technical_name` = 'order.state'),
                order_number = :orderNumber,
                currency_id = :defaultCurrency,
                language_id = :defaultLanguage,
                sales_channel_id = :defaultSalesChannel,
                billing_address_id = :billingAddressId,
                billing_address_version_id = :defaultVersion,
                price = '{}',
                order_date_time = NOW(),
                shipping_costs = '{}',
                created_at = NOW();
        SQL, [
            'orderId' => Uuid::fromHexToBytes($orderId),
            'orderNumber' => Uuid::randomHex(),
            'defaultVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'defaultCurrency' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'defaultLanguage' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'defaultSalesChannel' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'billingAddressId' => Uuid::randomBytes(),
        ]);

        return $orderId;
    }
}
