<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\SalesChannel\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelCreator::class)]
class SalesChannelCreatorTest extends TestCase
{
    public function testCreatesSalesChannelWithProvidedValuesAndMappings(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class);
        $creator = new SalesChannelCreator(
            static::createStub(DefinitionInstanceRegistry::class),
            $salesChannelRepository,
            StaticEntityRepository::of(PaymentMethodCollection::class),
            StaticEntityRepository::of(ShippingMethodCollection::class),
            StaticEntityRepository::of(CountryCollection::class),
            StaticEntityRepository::of(CategoryCollection::class),
        );

        $id = Uuid::randomHex();
        $typeId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $paymentMethodId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $countryId = Uuid::randomHex();
        $customerGroupId = Uuid::randomHex();
        $navigationCategoryId = Uuid::randomHex();
        $accessKey = $creator->createSalesChannel(
            id: $id,
            name: 'Test channel',
            typeId: $typeId,
            languageId: $languageId,
            currencyId: $currencyId,
            paymentMethodId: $paymentMethodId,
            shippingMethodId: $shippingMethodId,
            countryId: $countryId,
            customerGroupId: $customerGroupId,
            navigationCategoryId: $navigationCategoryId,
            currencies: [$currencyId, $currencyId],
            languages: [],
            shippingMethods: [$shippingMethodId],
            paymentMethods: [],
            countries: [$countryId],
            overwrites: ['accessKey' => 'provided-access-key'],
        );

        static::assertSame('provided-access-key', $accessKey);
        static::assertCount(1, $salesChannelRepository->creates);
        static::assertSame([
            'id' => $id,
            'name' => 'Test channel',
            'typeId' => $typeId,
            'accessKey' => 'provided-access-key',
            'languageId' => $languageId,
            'currencyId' => $currencyId,
            'paymentMethodId' => $paymentMethodId,
            'shippingMethodId' => $shippingMethodId,
            'countryId' => $countryId,
            'customerGroupId' => $customerGroupId,
            'navigationCategoryId' => $navigationCategoryId,
            'currencies' => [['id' => $currencyId]],
            'languages' => [['id' => $languageId]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'countries' => [['id' => $countryId]],
        ], $salesChannelRepository->creates[0][0]);
    }

    public function testUsesAllEntityIdsWhenMappingsAreNotProvided(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class);
        $repositories = [
            'currency' => StaticEntityRepository::of(SalesChannelCollection::class, [['currency-id']]),
            'language' => StaticEntityRepository::of(SalesChannelCollection::class, [['language-id']]),
            'shipping_method' => StaticEntityRepository::of(SalesChannelCollection::class, [['shipping-method-id']]),
            'payment_method' => StaticEntityRepository::of(SalesChannelCollection::class, [['payment-method-id']]),
            'country' => StaticEntityRepository::of(SalesChannelCollection::class, [['country-id']]),
            CustomerGroupDefinition::ENTITY_NAME => StaticEntityRepository::of(SalesChannelCollection::class, [['customer-group-id']]),
        ];

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('getRepository')->willReturnCallback(
            static fn (string $entity): StaticEntityRepository => $repositories[$entity]
        );

        $creator = new SalesChannelCreator(
            $definitionRegistry,
            $salesChannelRepository,
            StaticEntityRepository::of(PaymentMethodCollection::class, [['payment-default']]),
            StaticEntityRepository::of(ShippingMethodCollection::class, [['shipping-default']]),
            StaticEntityRepository::of(CountryCollection::class, [['country-default']]),
            StaticEntityRepository::of(CategoryCollection::class, [['root-category']]),
        );

        $creator->createSalesChannel(
            id: 'sales-channel-id',
            name: 'All IDs channel',
            typeId: 'type-id',
            paymentMethodId: 'payment-default',
            shippingMethodId: 'shipping-default',
            countryId: 'country-default',
            navigationCategoryId: 'root-category',
        );

        static::assertSame([['id' => 'currency-id']], $salesChannelRepository->creates[0][0]['currencies']);
        static::assertSame([['id' => 'language-id']], $salesChannelRepository->creates[0][0]['languages']);
        static::assertSame('customer-group-id', $salesChannelRepository->creates[0][0]['customerGroupId']);
    }
}
