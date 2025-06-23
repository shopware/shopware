<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\OrderDocumentCriteriaFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderDocumentCriteriaFactory::class)]
class OrderDocumentCriteriaFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $id = Uuid::randomHex();

        $criteria = OrderDocumentCriteriaFactory::create([$id], 'test');

        static::assertSame($id, $criteria->getIds()[0]);

        $associations = $criteria->getAssociations();

        static::assertArrayHasKey('lineItems', $associations);
        static::assertArrayHasKey('primaryOrderTransaction', $associations);
        static::assertArrayHasKey('currency', $associations);
        static::assertArrayHasKey('language', $associations);
        static::assertArrayHasKey('addresses', $associations);
        static::assertArrayHasKey('orderCustomer', $associations);

        $primaryOrderTransactionCriteria = $associations['primaryOrderTransaction'];
        static::assertInstanceOf(Criteria::class, $primaryOrderTransactionCriteria);
        static::assertInstanceOf(Criteria::class, $primaryOrderTransactionCriteria->getAssociations()['paymentMethod']);
        static::assertInstanceOf(Criteria::class, $primaryOrderTransactionCriteria->getAssociations()['stateMachineState']);

        $languageCriteria = $associations['language'];
        static::assertInstanceOf(Criteria::class, $languageCriteria);
        static::assertInstanceOf(Criteria::class, $languageCriteria->getAssociations()['locale']);

        $addressesCriteria = $associations['addresses'];
        static::assertInstanceOf(Criteria::class, $addressesCriteria);
        static::assertInstanceOf(Criteria::class, $addressesCriteria->getAssociations()['country']);

        $orderCustomerCriteria = $associations['orderCustomer'];
        static::assertInstanceOf(Criteria::class, $orderCustomerCriteria);
        static::assertInstanceOf(Criteria::class, $orderCustomerCriteria->getAssociations()['customer']);

        $deliveryCriteria = $associations['deliveries'];
        static::assertInstanceOf(Criteria::class, $deliveryCriteria);
        static::assertInstanceOf(Criteria::class, $deliveryCriteria->getAssociations()['shippingMethod']);
        static::assertInstanceOf(Criteria::class, $deliveryCriteria->getAssociations()['positions']);
        static::assertInstanceOf(Criteria::class, $deliveryCriteria->getAssociations()['shippingOrderAddress']);

        $shippingAddressCriteria = $deliveryCriteria->getAssociations()['shippingOrderAddress'];
        static::assertInstanceOf(Criteria::class, $shippingAddressCriteria->getAssociations()['country']);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testCreate will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCreateDeprecated(): void
    {
        $id = Uuid::randomHex();

        $criteria = OrderDocumentCriteriaFactory::create([$id], 'test');

        static::assertSame($id, $criteria->getIds()[0]);

        $associations = $criteria->getAssociations();

        static::assertArrayHasKey('lineItems', $associations);
        static::assertArrayHasKey('primaryOrderTransaction', $associations);
        static::assertArrayHasKey('transactions', $associations);
        static::assertArrayHasKey('currency', $associations);
        static::assertArrayHasKey('language', $associations);
        static::assertArrayHasKey('addresses', $associations);
        static::assertArrayHasKey('orderCustomer', $associations);

        $primaryOrderTransactionCriteria = $associations['primaryOrderTransaction'];
        static::assertInstanceOf(Criteria::class, $primaryOrderTransactionCriteria);
        static::assertInstanceOf(Criteria::class, $primaryOrderTransactionCriteria->getAssociations()['paymentMethod']);
        static::assertInstanceOf(Criteria::class, $primaryOrderTransactionCriteria->getAssociations()['stateMachineState']);

        $transactionCriteria = $associations['transactions'];
        static::assertInstanceOf(Criteria::class, $transactionCriteria);
        static::assertInstanceOf(Criteria::class, $transactionCriteria->getAssociations()['paymentMethod']);
        static::assertInstanceOf(Criteria::class, $transactionCriteria->getAssociations()['stateMachineState']);

        $languageCriteria = $associations['language'];
        static::assertInstanceOf(Criteria::class, $languageCriteria);
        static::assertInstanceOf(Criteria::class, $languageCriteria->getAssociations()['locale']);

        $addressesCriteria = $associations['addresses'];
        static::assertInstanceOf(Criteria::class, $addressesCriteria);
        static::assertInstanceOf(Criteria::class, $addressesCriteria->getAssociations()['country']);

        $orderCustomerCriteria = $associations['orderCustomer'];
        static::assertInstanceOf(Criteria::class, $orderCustomerCriteria);
        static::assertInstanceOf(Criteria::class, $orderCustomerCriteria->getAssociations()['customer']);

        $deliveryCriteria = $associations['deliveries'];
        static::assertInstanceOf(Criteria::class, $deliveryCriteria);
        static::assertInstanceOf(Criteria::class, $deliveryCriteria->getAssociations()['shippingMethod']);
        static::assertInstanceOf(Criteria::class, $deliveryCriteria->getAssociations()['positions']);
        static::assertInstanceOf(Criteria::class, $deliveryCriteria->getAssociations()['shippingOrderAddress']);

        $shippingAddressCriteria = $deliveryCriteria->getAssociations()['shippingOrderAddress'];
        static::assertInstanceOf(Criteria::class, $shippingAddressCriteria->getAssociations()['country']);
    }
}
