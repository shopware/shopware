<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class LegacyOrderAssociationFallbackTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testChangedPaymentSubtitleUsesPrimaryTransactionInMajorMode(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setTranslated(['name' => 'Test payment']);

        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $transaction->setPaymentMethod($paymentMethod);

        $order = new LegacyAssociationTrackingOrderEntity();
        $order->setPrimaryOrderTransaction($transaction);
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $output = $twig->createTemplate('{{ block(\'page_checkout_finish_subtitle\', \'@Storefront/storefront/page/checkout/finish/finish-details.html.twig\') }}')->render([
            'page' => [
                'changedPayment' => true,
                'order' => $order,
            ],
        ]);

        static::assertStringContainsString('Test payment', $output);
        static::assertSame(Feature::isActive('v6.8.0.0') ? 0 : 1, $order->getLegacyAssociationReadCount('getTransactions'));
    }

    #[DataProvider('legacyFallbackBlocks')]
    public function testLegacyAssociationIsNotReadInMajorMode(string $template, string $block, string $legacyGetter): void
    {
        $order = new LegacyAssociationTrackingOrderEntity();

        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $output = $twig->createTemplate(\sprintf('{{ block(\'%s\', \'%s\') }}', $block, $template))->render([
            'order' => $order,
        ]);

        static::assertSame('', trim($output));
        static::assertSame(Feature::isActive('v6.8.0.0') ? 0 : 1, $order->getLegacyAssociationReadCount($legacyGetter));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function legacyFallbackBlocks(): iterable
    {
        $itemTemplate = '@Storefront/storefront/page/account/order-history/order-item.html.twig';
        $detailTemplate = '@Storefront/storefront/page/account/order-history/order-detail.html.twig';

        yield 'order item shipping status header' => [$itemTemplate, 'page_account_order_item_order_table_header_cell_shipping_status', 'getDeliveries'];
        yield 'order item shipping method header' => [$itemTemplate, 'page_account_order_item_order_table_header_cell_shipping_method', 'getDeliveries'];
        yield 'order item shipping status' => [$itemTemplate, 'page_account_order_item_order_table_body_cell_shipping_status', 'getDeliveries'];
        yield 'order item shipping method' => [$itemTemplate, 'page_account_order_item_order_table_body_cell_shipping_method', 'getDeliveries'];
        yield 'order detail payment method' => [$detailTemplate, 'page_account_order_item_detail_payment_method', 'getTransactions'];
        yield 'order detail shipping method' => [$detailTemplate, 'page_account_order_item_detail_shipping_method', 'getDeliveries'];
    }
}

/**
 * @internal
 */
#[Package('discovery')]
final class LegacyAssociationTrackingOrderEntity extends OrderEntity
{
    private int $deliveryReads = 0;

    private int $transactionReads = 0;

    public function getDeliveries(): OrderDeliveryCollection
    {
        ++$this->deliveryReads;

        return new OrderDeliveryCollection();
    }

    public function getTransactions(): OrderTransactionCollection
    {
        ++$this->transactionReads;

        return parent::getTransactions() ?? new OrderTransactionCollection();
    }

    public function getLegacyAssociationReadCount(string $getter): int
    {
        return $getter === 'getDeliveries' ? $this->deliveryReads : $this->transactionReads;
    }
}
