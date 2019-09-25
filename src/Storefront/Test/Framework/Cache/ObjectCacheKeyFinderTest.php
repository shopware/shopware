<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Storefront\Framework\Cache\ObjectCacheKeyFinder;

class ObjectCacheKeyFinderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ObjectCacheKeyFinder
     */
    private $finder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->finder = $this->getContainer()->get(ObjectCacheKeyFinder::class);
    }

    public function testWithSalesChannelProduct(): void
    {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('product.repository');

        $id = Uuid::randomHex();
        $repo->create([
            [
                'id' => $id,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['id' => $id, 'name' => 'test'],
                'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
                'visibilities' => [
                    ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create($id, Defaults::SALES_CHANNEL);

        /** @var SalesChannelRepositoryInterface $repo */
        $repo = $this->getContainer()->get('sales_channel.product.repository');

        $products = $repo->search(new Criteria([$id]), $context);

        static::assertTrue($products->has($id));

        $product = $products->get($id);

        $keys = $this->finder->find([$product], $context);

        $expected = array_merge(
            $this->getContextCacheKeys($context),
            [
                'product-' . $id,
                'tax-' . $id,
            ]
        );

        foreach ($expected as $key) {
            static::assertContains($key, $keys);
        }
    }

    public function testRecursion(): void
    {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('property_group.repository');

        $id = Uuid::randomHex();

        $repo->create([
            [
                'id' => $id,
                'name' => 'color',
                'options' => [
                    ['name' => 'red'],
                    ['name' => 'green'],
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addAssociation('options');
        $group = $repo
            ->search($criteria, Context::createDefaultContext())
            ->first();

        /** @var PropertyGroupEntity $group */
        static::assertInstanceOf(PropertyGroupEntity::class, $group);
        static::assertInstanceOf(PropertyGroupOptionCollection::class, $group->getOptions());

        static::assertCount(2, $group->getOptions());

        $optionKeys = [];
        foreach ($group->getOptions() as $option) {
            $optionKeys[] = 'property_group_option-' . $option->getId();
            $option->setGroup($group);
        }

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $expected = array_merge(
            ['property_group-' . $group->getId()],
            $optionKeys,
            $this->getContextCacheKeys($context)
        );

        $keys = $this->finder->find([$group], $context);
        foreach ($expected as $key) {
            static::assertContains($key, $keys);
        }
    }

    private function getContextCacheKeys(SalesChannelContext $context)
    {
        $taxKeys = array_map(function (TaxEntity $tax) {
            return 'tax-' . $tax->getId();
        }, $context->getTaxRules()->getElements());

        return array_merge(array_values($taxKeys), [
            'customer_group-' . $context->getCurrentCustomerGroup()->getId(),
            'currency-' . $context->getCurrency()->getId(),
            'payment_method-' . $context->getPaymentMethod()->getId(),
            'shipping_method-' . $context->getShippingMethod()->getId(),
            'delivery_time-' . $context->getShippingMethod()->getDeliveryTimeId(),
            'country-' . $context->getShippingLocation()->getCountry()->getId(),
        ]);
    }
}
