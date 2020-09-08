<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityForeignKeyResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Connection
     */
    private $testConnection;

    /**
     * @var EntityForeignKeyResolver
     */
    private $entityForeignKeyResolver;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function testItCreatesEventsForWriteProtectedCascadeDeletes(): void
    {
        $categoryIds = [
            'parentCategory' => Uuid::randomHex(),
            'childCategory' => Uuid::randomHex(),
            'secondRootCategory' => Uuid::randomHex(),
        ];

        $productId = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $context = Context::createDefaultContext();

        $productRepository->create([
            [
                'id' => $productId,
                'name' => 'produt to delete',
                'productNumber' => 'sw-test-1',
                'price' => [
                    [
                        'gross' => 200,
                        'net' => 190,
                        'linked' => true,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
                'stock' => 100,
                'tax' => [
                    'name' => 'testTax',
                    'taxRate' => 10,
                ],
                'categories' => [
                    [
                        'id' => $categoryIds['parentCategory'],
                        'name' => 'parent category',
                    ],
                    [
                        'id' => $categoryIds['childCategory'],
                        'name' => 'child category',
                        'parentId' => $categoryIds['parentCategory'],
                    ],
                    [
                        'id' => $categoryIds['secondRootCategory'],
                        'name' => 'second root',
                    ],
                ],
            ],
        ], $context);

        $deletedEvent = $productRepository->delete([['id' => $productId]], $context);

        $deletedProduct = $deletedEvent->getPrimaryKeys('product');
        $deletedCategories = $deletedEvent->getPrimaryKeys('category');
        $deletedCategoriesRo = $deletedEvent->getPrimaryKeys('product_category_tree');

        static::assertEquals($productId, $deletedProduct[0]);
        static::assertEmpty($deletedCategories);
        static::assertCount(3, $deletedCategoriesRo);

        foreach ($deletedCategoriesRo as $deletedRo) {
            foreach ($categoryIds as $index => $id) {
                if ($id === $deletedRo['categoryId']) {
                    unset($categoryIds[$index]);
                }
            }
        }

        static::assertEmpty($categoryIds);
    }

    public function testNestedCascades(): void
    {
        $ids = new IdsCollection();

        $this->createOrder($ids, 1);

        $this->createOrder($ids, 2);

        $context = Context::createDefaultContext();

        $definition = $this->getContainer()->get(OrderDefinition::class);

        $pk = [
            ['id' => $ids->get('order1')],
            ['id' => $ids->get('order2')],
        ];

        $affected = $this->getContainer()->get(EntityForeignKeyResolver::class)
            ->getAffectedDeletes($definition, $pk, $context);

        static::assertCount(5, $affected);

        static::assertArrayHasKey('order_customer', $affected);
        static::assertArrayHasKey('order_address', $affected);
        static::assertArrayHasKey('order_delivery', $affected);
        static::assertArrayHasKey('order_line_item', $affected);
        static::assertArrayHasKey('order_delivery_position', $affected);

        static::assertCount(4, $affected['order_address']);
        static::assertContains($ids->get('shipping-address1'), $affected['order_address']);
        static::assertContains($ids->get('shipping-address2'), $affected['order_address']);
        static::assertContains($ids->get('billing-address1'), $affected['order_address']);
        static::assertContains($ids->get('billing-address2'), $affected['order_address']);

        static::assertCount(2, $affected['order_customer']);
        static::assertContains($ids->get('customer1'), $affected['order_customer']);
        static::assertContains($ids->get('customer2'), $affected['order_customer']);

        static::assertCount(2, $affected['order_line_item']);
        static::assertContains($ids->get('line-item1'), $affected['order_line_item']);
        static::assertContains($ids->get('line-item2'), $affected['order_line_item']);

        static::assertCount(2, $affected['order_delivery']);
        static::assertContains($ids->get('delivery1'), $affected['order_delivery']);
        static::assertContains($ids->get('delivery2'), $affected['order_delivery']);

        static::assertCount(2, $affected['order_delivery_position']);
        static::assertContains($ids->get('position1'), $affected['order_delivery_position']);
        static::assertContains($ids->get('position2'), $affected['order_delivery_position']);
    }

    private function getStateId(string $state, string $machine)
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchColumn('
                SELECT LOWER(HEX(state_machine_state.id))
                FROM state_machine_state
                    INNER JOIN  state_machine
                    ON state_machine.id = state_machine_state.state_machine_id
                    AND state_machine.technical_name = :machine
                WHERE state_machine_state.technical_name = :state
            ', [
                'state' => $state,
                'machine' => $machine,
            ]);
    }

    private function createOrder(IdsCollection $ids, int $i): void
    {
        $data = [
            'id' => $ids->create('order' . $i),
            'billingAddressId' => $ids->create('billing-address' . $i),
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'orderDateTime' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'currencyFactor' => 1,
            'stateId' => $this->getStateId('open', 'order.state'),
            'price' => new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'id' => $ids->get('customer' . $i),
                'salutationId' => $this->getValidSalutationId(),
                'email' => 'test',
                'firstName' => 'test',
                'lastName' => 'test',
            ],
            'addresses' => [
                [
                    'id' => $ids->create('billing-address' . $i),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'asd',
                    'lastName' => 'asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
                [
                    'id' => $ids->create('shipping-address' . $i),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'asd',
                    'lastName' => 'asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
            ],
            'lineItems' => [
                [
                    'id' => $ids->create('line-item' . $i),
                    'identifier' => $ids->create('line-item' . $i),
                    'quantity' => 1,
                    'label' => 'label',
                    'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
            'deliveries' => [
                [
                    'id' => $ids->create('delivery' . $i),
                    'shippingOrderAddressId' => $ids->create('shipping-address' . $i),
                    'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                    'stateId' => $this->getStateId('open', 'order_delivery.state'),
                    'trackingCodes' => [],
                    'shippingDateEarliest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingDateLatest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'positions' => [
                        [
                            'id' => $ids->create('position' . $i),
                            'orderLineItemId' => $ids->create('line-item' . $i),
                            'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        ],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->getContainer()->get('order.repository')
            ->create([$data], $context);
    }
}
