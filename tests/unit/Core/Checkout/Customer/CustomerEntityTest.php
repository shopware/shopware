<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerEntity::class)]
class CustomerEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testAccessorsRoundTrip(): void
    {
        $group = new CustomerGroupEntity();
        $requestedGroup = new CustomerGroupEntity();
        $salesChannel = new SalesChannelEntity();
        $boundSalesChannel = new SalesChannelEntity();
        $language = new LanguageEntity();
        $paymentMethod = new PaymentMethodEntity();
        $salutation = new SalutationEntity();
        $billingAddress = new CustomerAddressEntity();
        $shippingAddress = new CustomerAddressEntity();
        $activeBillingAddress = new CustomerAddressEntity();
        $activeShippingAddress = new CustomerAddressEntity();
        $addresses = new CustomerAddressCollection();
        $orderCustomers = new OrderCustomerCollection();
        $tags = new TagCollection();
        $promotions = new PromotionCollection();
        $reviews = new ProductReviewCollection();
        $recovery = new CustomerRecoveryEntity();
        $wishlists = new CustomerWishlistCollection();
        $createdBy = new UserEntity();
        $updatedBy = new UserEntity();
        $emailSentDate = new \DateTimeImmutable('2024-01-01 10:00:00');
        $confirmDate = new \DateTimeImmutable('2024-01-02 10:00:00');
        $firstLogin = new \DateTimeImmutable('2024-01-03 10:00:00');
        $lastLogin = new \DateTimeImmutable('2024-01-04 10:00:00');
        $birthday = new \DateTimeImmutable('1990-05-06');
        $lastOrderDate = new \DateTimeImmutable('2024-01-05 10:00:00');

        $customer = new CustomerEntity();
        $customer->setGroupId('group-id');
        $customer->setSalesChannelId('sales-channel-id');
        $customer->setLanguageId('language-id');
        $customer->setLastPaymentMethodId('payment-method-id');
        $customer->setDefaultBillingAddressId('billing-address-id');
        $customer->setDefaultShippingAddressId('shipping-address-id');
        $customer->setCustomerNumber('10001');
        $customer->setSalutationId('salutation-id');
        $customer->setFirstName('Ada');
        $customer->setLastName('Lovelace');
        $customer->setCompany('Analytical Engines');
        $customer->setEmail('ada@example.com');
        $customer->setTitle('Dr.');
        $customer->setVatIds(['DE123456789']);
        $customer->setActive(true);
        $customer->setDoubleOptInRegistration(true);
        $customer->setDoubleOptInEmailSentDate($emailSentDate);
        $customer->setDoubleOptInConfirmDate($confirmDate);
        $customer->setHash('hash');
        $customer->setGuest(false);
        $customer->setFirstLogin($firstLogin);
        $customer->setLastLogin($lastLogin);
        $customer->setBirthday($birthday);
        $customer->setLastOrderDate($lastOrderDate);
        $customer->setOrderCount(3);
        $customer->setOrderTotalAmount(99.5);
        $customer->setReviewCount(2);
        $customer->setGroup($group);
        $customer->setSalesChannel($salesChannel);
        $customer->setLanguage($language);
        $customer->setLastPaymentMethod($paymentMethod);
        $customer->setSalutation($salutation);
        $customer->setDefaultBillingAddress($billingAddress);
        $customer->setDefaultShippingAddress($shippingAddress);
        $customer->setActiveBillingAddress($activeBillingAddress);
        $customer->setActiveShippingAddress($activeShippingAddress);
        $customer->setAddresses($addresses);
        $customer->setOrderCustomers($orderCustomers);
        $customer->setAutoIncrement(7);
        $customer->setTags($tags);
        $customer->setTagIds(['tag-id']);
        $customer->setPromotions($promotions);
        $customer->setProductReviews($reviews);
        $customer->setRecoveryCustomer($recovery);
        $customer->setAffiliateCode('affiliate');
        $customer->setCampaignCode('campaign');
        $customer->setRemoteAddress('127.0.0.1');
        $customer->setRequestedGroupId('requested-group-id');
        $customer->setRequestedGroup($requestedGroup);
        $customer->setBoundSalesChannelId('bound-sales-channel-id');
        $customer->setBoundSalesChannel($boundSalesChannel);
        $customer->setAccountType('business');
        $customer->setWishlists($wishlists);
        $customer->setCreatedById('created-by-id');
        $customer->setCreatedBy($createdBy);
        $customer->setUpdatedById('updated-by-id');
        $customer->setUpdatedBy($updatedBy);

        static::assertSame('group-id', $customer->getGroupId());
        static::assertSame('sales-channel-id', $customer->getSalesChannelId());
        static::assertSame('language-id', $customer->getLanguageId());
        static::assertSame('payment-method-id', $customer->getLastPaymentMethodId());
        static::assertSame('billing-address-id', $customer->getDefaultBillingAddressId());
        static::assertSame('shipping-address-id', $customer->getDefaultShippingAddressId());
        static::assertSame('10001', $customer->getCustomerNumber());
        static::assertSame('salutation-id', $customer->getSalutationId());
        static::assertSame('Ada', $customer->getFirstName());
        static::assertSame('Lovelace', $customer->getLastName());
        static::assertSame('Analytical Engines', (string) $customer);
        static::assertSame('Analytical Engines', $customer->getCompany());
        static::assertSame('ada@example.com', $customer->getEmail());
        static::assertSame('Dr.', $customer->getTitle());
        static::assertSame(['DE123456789'], $customer->getVatIds());
        static::assertTrue($customer->getActive());
        static::assertTrue($customer->getDoubleOptInRegistration());
        static::assertSame($emailSentDate, $customer->getDoubleOptInEmailSentDate());
        static::assertSame($confirmDate, $customer->getDoubleOptInConfirmDate());
        static::assertSame('hash', $customer->getHash());
        static::assertFalse($customer->getGuest());
        static::assertSame($firstLogin, $customer->getFirstLogin());
        static::assertSame($lastLogin, $customer->getLastLogin());
        static::assertSame($birthday, $customer->getBirthday());
        static::assertSame($lastOrderDate, $customer->getLastOrderDate());
        static::assertSame(3, $customer->getOrderCount());
        static::assertSame(99.5, $customer->getOrderTotalAmount());
        static::assertSame(2, $customer->getReviewCount());
        static::assertSame($group, $customer->getGroup());
        static::assertSame($salesChannel, $customer->getSalesChannel());
        static::assertSame($language, $customer->getLanguage());
        static::assertSame($paymentMethod, $customer->getLastPaymentMethod());
        static::assertSame($salutation, $customer->getSalutation());
        static::assertSame($billingAddress, $customer->getDefaultBillingAddress());
        static::assertSame($shippingAddress, $customer->getDefaultShippingAddress());
        static::assertSame($activeBillingAddress, $customer->getActiveBillingAddress());
        static::assertSame($activeShippingAddress, $customer->getActiveShippingAddress());
        static::assertSame($addresses, $customer->getAddresses());
        static::assertSame($orderCustomers, $customer->getOrderCustomers());
        static::assertSame(7, $customer->getAutoIncrement());
        static::assertSame($tags, $customer->getTags());
        static::assertSame(['tag-id'], $customer->getTagIds());
        static::assertSame($promotions, $customer->getPromotions());
        static::assertSame($reviews, $customer->getProductReviews());
        static::assertSame($recovery, $customer->getRecoveryCustomer());
        static::assertSame('affiliate', $customer->getAffiliateCode());
        static::assertSame('campaign', $customer->getCampaignCode());
        static::assertSame('127.0.0.1', $customer->getRemoteAddress());
        static::assertSame('requested-group-id', $customer->getRequestedGroupId());
        static::assertSame($requestedGroup, $customer->getRequestedGroup());
        static::assertSame('bound-sales-channel-id', $customer->getBoundSalesChannelId());
        static::assertSame($boundSalesChannel, $customer->getBoundSalesChannel());
        static::assertSame('business', $customer->getAccountType());
        static::assertSame($wishlists, $customer->getWishlists());
        static::assertSame('created-by-id', $customer->getCreatedById());
        static::assertSame($createdBy, $customer->getCreatedBy());
        static::assertSame('updated-by-id', $customer->getUpdatedById());
        static::assertSame($updatedBy, $customer->getUpdatedBy());
    }

    public function testHasLegacyPasswordNeedsBothHashAndEncoder(): void
    {
        $customer = new CustomerEntity();
        static::assertFalse($customer->hasLegacyPassword());

        $customer->setLegacyPassword('hash');
        static::assertFalse($customer->hasLegacyPassword());

        $customer->setLegacyEncoder('md5');
        static::assertTrue($customer->hasLegacyPassword());
    }

    #[DataProvider('nameProvider')]
    public function testToString(string $accountType, string $firstName, string $lastName, ?string $company, string $expected): void
    {
        $customer = new CustomerEntity();
        $customer->setAccountType($accountType);
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);

        if ($company !== null) {
            $customer->setCompany($company);
        }

        static::assertSame($expected, (string) $customer);
    }

    public function testToStringWithoutAccountTypeUsesThePersonName(): void
    {
        $customer = new CustomerEntity();
        $customer->setFirstName('Ada');
        $customer->setLastName('Lovelace');

        static::assertSame('Ada Lovelace', (string) $customer);
    }

    /**
     * @return \Generator<string, array{string, string, string, string|null, string}>
     */
    public static function nameProvider(): \Generator
    {
        yield 'private account uses the person name' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, 'Ada', 'Lovelace', null, 'Ada Lovelace',
        ];

        yield 'private account ignores the company' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, 'Ada', 'Lovelace', 'Analytical Engines', 'Ada Lovelace',
        ];

        yield 'business account uses the company' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'Ada', 'Lovelace', 'Analytical Engines', 'Analytical Engines',
        ];

        yield 'business account without a company falls back to the person name' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'Ada', 'Lovelace', null, 'Ada Lovelace',
        ];

        yield 'business account with a blank company falls back to the person name' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'Ada', 'Lovelace', '   ', 'Ada Lovelace',
        ];

        yield 'business account without a person name uses the company' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, '', '', 'Analytical Engines', 'Analytical Engines',
        ];

        yield 'empty person name is not padded with a space' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, '', '', null, '',
        ];
    }

    /**
     * @param \Closure(CustomerEntity): void $write
     * @param \Closure(CustomerEntity): mixed $read
     */
    #[TestDox('$_dataName is readable outside of a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsReadableOutsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $customer = $this->customerWithInternalProperties();
        $write($customer);

        static::assertSame($expected, $read($customer));
    }

    /**
     * @param \Closure(CustomerEntity): void $write
     * @param \Closure(CustomerEntity): mixed $read
     */
    #[TestDox('$_dataName is guarded inside a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsGuardedInsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $customer = $this->customerWithInternalProperties();
        $write($customer);

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed($property, CustomerEntity::class));
        $read($customer);
    }

    /**
     * @return \Generator<string, array{0: \Closure(CustomerEntity): void, 1: \Closure(CustomerEntity): mixed, 2: mixed, 3: string}>
     */
    public static function internalPropertyProvider(): \Generator
    {
        yield 'password' => [
            static fn (CustomerEntity $customer) => $customer->setPassword('secret'),
            static fn (CustomerEntity $customer) => $customer->getPassword(),
            'secret',
            'password',
        ];

        yield 'newsletterSalesChannelIds' => [
            static fn (CustomerEntity $customer) => $customer->setNewsletterSalesChannelIds(['a' => 'b']),
            static fn (CustomerEntity $customer) => $customer->getNewsletterSalesChannelIds(),
            ['a' => 'b'],
            'newsletterSalesChannelIds',
        ];

        yield 'legacyEncoder' => [
            static fn (CustomerEntity $customer) => $customer->setLegacyEncoder('md5'),
            static fn (CustomerEntity $customer) => $customer->getLegacyEncoder(),
            'md5',
            'legacyEncoder',
        ];

        yield 'legacyPassword' => [
            static fn (CustomerEntity $customer) => $customer->setLegacyPassword('hash'),
            static fn (CustomerEntity $customer) => $customer->getLegacyPassword(),
            'hash',
            'legacyPassword',
        ];
    }

    private function customerWithInternalProperties(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->internalSetEntityData(
            CustomerDefinition::ENTITY_NAME,
            new FieldVisibility(['password', 'newsletterSalesChannelIds', 'legacyEncoder', 'legacyPassword']),
        );

        return $customer;
    }
}
