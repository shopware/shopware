<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1624202045AddValueOfOrdersToCustomerTable;

class Migration1624202045AddValueOfOrdersToCustomerTableTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testUpdateValueOfOrders(): void
    {
        $migration = new Migration1624202045AddValueOfOrdersToCustomerTable();
        $migration->update($this->getContainer()->get(Connection::class));

        $customerId = $this->createCustomer();
        $criteria = new Criteria([$customerId]);

        /** @var CustomerEntity $customer */
        $customer = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals(700, $customer->getOrderTotalAmount());
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $orderId1 = Uuid::randomHex();
        $orderId2 = Uuid::randomHex();
        $stateId = $this->getStateId();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Thuy',
            'lastName' => 'Le',
            'customerNumber' => '1337',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Thuy',
                    'lastName' => 'Le',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
            'orderCustomers' => [
                [
                    'id' => Uuid::randomHex(),
                    'customerId' => $customerId,
                    'orderId' => $orderId1,
                    'email' => 'test@gmail.com',
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Thuy',
                    'lastName' => 'Le',
                    'order' => [
                        'id' => $orderId1,
                        'billingAddressId' => Uuid::randomHex(),
                        'currencyId' => Defaults::CURRENCY,
                        'salesChannelId' => Defaults::SALES_CHANNEL,
                        'currencyFactor' => 1,
                        'orderDateTime' => new \DateTime(),
                        'stateId' => $stateId,
                        'price' => [
                            'netPrice' => 500.0,
                            'totalPrice' => 500.0,
                            'calculatedTaxes' => [
                                0 => [
                                    'tax' => 0.0,
                                    'taxRate' => 0.0,
                                    'price' => 500.0,
                                    'extensions' => [
                                    ],
                                ],
                            ],
                            'taxRules' => [
                                0 => [
                                    'taxRate' => 0.0,
                                    'percentage' => 100.0,
                                    'extensions' => [
                                    ],
                                ],
                            ],
                            'positionPrice' => 500.0,
                            'taxStatus' => 'gross',
                            'rawTotal' => 500.0,
                            'extensions' => [
                            ],
                        ],
                        'shippingCosts' => [
                            'unitPrice' => 0.0,
                            'quantity' => 1,
                            'totalPrice' => 0.0,
                            'calculatedTaxes' => [
                                0 => [
                                    'tax' => 0.0,
                                    'taxRate' => 0.0,
                                    'price' => 0.0,
                                    'extensions' => [
                                    ],
                                ],
                            ],
                            'taxRules' => [
                                0 => [
                                    'taxRate' => 0.0,
                                    'percentage' => 100.0,
                                    'extensions' => [
                                    ],
                                ],
                            ],
                            'referencePrice' => null,
                            'listPrice' => null,
                            'extensions' => [
                            ],
                        ],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'customerId' => $customerId,
                    'orderId' => $orderId2,
                    'email' => 'test@gmail.com',
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Thuy',
                    'lastName' => 'Le',
                    'order' => [
                        'id' => $orderId2,
                        'billingAddressId' => Uuid::randomHex(),
                        'currencyId' => Defaults::CURRENCY,
                        'salesChannelId' => Defaults::SALES_CHANNEL,
                        'currencyFactor' => 1,
                        'orderDateTime' => new \DateTime(),
                        'stateId' => $stateId,
                        'price' => [
                            'netPrice' => 200.0,
                            'totalPrice' => 200.0,
                            'calculatedTaxes' => [
                                0 => [
                                    'tax' => 0.0,
                                    'taxRate' => 0.0,
                                    'price' => 200.0,
                                    'extensions' => [
                                    ],
                                ],
                            ],
                            'taxRules' => [
                                0 => [
                                    'taxRate' => 0.0,
                                    'percentage' => 100.0,
                                    'extensions' => [
                                    ],
                                ],
                            ],
                            'positionPrice' => 200.0,
                            'taxStatus' => 'gross',
                            'rawTotal' => 200.0,
                            'extensions' => [
                            ],
                        ],
                        'shippingCosts' => [
                            'unitPrice' => 0.0,
                            'quantity' => 1,
                            'totalPrice' => 0.0,
                            'calculatedTaxes' => [
                                0 => [
                                    'tax' => 0.0,
                                    'taxRate' => 0.0,
                                    'price' => 0.0,
                                    'extensions' => [
                                    ],
                                ],
                            ],
                            'taxRules' => [
                                0 => [
                                    'taxRate' => 0.0,
                                    'percentage' => 100.0,
                                    'extensions' => [
                                    ],
                                ],
                            ],
                            'referencePrice' => null,
                            'listPrice' => null,
                            'extensions' => [
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('customer.repository')->create([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function getStateId(): string
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM state_machine_state LIMIT 1');
    }
}
