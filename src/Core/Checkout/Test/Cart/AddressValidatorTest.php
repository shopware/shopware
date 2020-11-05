<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\AddressValidator;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Validator;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AddressValidatorTest extends TestCase
{
    use KernelTestBehaviour;
    use BasicTestDataBehaviour;

    private const ADDRESS_MODE_SHIPPING_SAME_AS_BILLING = -1;
    private const ADDRESS_MODE_VALID = 0;
    private const ADDRESS_MODE_VALIDATION_ERROR = 1;
    private const ADDRESS_MODE_COUNTRY_INACTIVE = 2;
    private const ADDRESS_MODE_COUNTRY_NOT_SHIPPABLE = 3;
    private const ADDRESS_MODE_COUNTRY_NOT_IN_SALES_CHANNEL = 4;

    public function tearDown(): void
    {
        $criteria = (new Criteria())->setLimit(2);
        $criteria->addFilter(new EqualsFilter('salesChannels.id', Defaults::SALES_CHANNEL));
        $context = Context::createDefaultContext();

        /** @var EntityRepositoryInterface $countryRepository */
        $countryRepository = $this->getContainer()->get('country.repository');

        $ids = $countryRepository->searchIds($criteria, $context)->getIds();

        $countryRepository->update(
            array_map(
                static function (string $id) {
                    return [
                        'id' => $id,
                        'active' => true,
                        'shippingAvailable' => true,
                    ];
                },
                $ids
            ),
            $context
        );
    }

    /**
     * @dataProvider dataProviderValidate
     */
    public function testValidate(int $billingAddressMode, int $shippingAddressMode, array $expectedErrors): void
    {
        $customerId = $this->createCustomer($billingAddressMode, $shippingAddressMode);
        $salesChannelContext = $this->createSalesChannelContext($customerId, $billingAddressMode, $shippingAddressMode);

        /** @var AddressValidator $addressValidator */
        $addressValidator = $this->getContainer()->get(AddressValidator::class);
        $validator = new Validator([$addressValidator]);

        $errors = $validator->validate(Generator::createCart(), $salesChannelContext);
        static::assertEqualsCanonicalizing($expectedErrors, array_map(static function (Error $error) {
            return $error->getMessageKey();
        }, $errors));

        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        $customerRepository->delete([['id' => $customerId]], $salesChannelContext->getContext());
    }

    public function dataProviderValidate(): array
    {
        return [
            [self::ADDRESS_MODE_VALID, self::ADDRESS_MODE_SHIPPING_SAME_AS_BILLING, []],
            [self::ADDRESS_MODE_VALID, self::ADDRESS_MODE_COUNTRY_NOT_IN_SALES_CHANNEL, ['shipping-address-blocked']],
            [self::ADDRESS_MODE_VALID, self::ADDRESS_MODE_COUNTRY_NOT_SHIPPABLE, ['shipping-address-blocked']],
            [self::ADDRESS_MODE_VALID, self::ADDRESS_MODE_COUNTRY_INACTIVE, ['shipping-address-blocked']],
            [self::ADDRESS_MODE_VALID, self::ADDRESS_MODE_VALIDATION_ERROR, ['shipping-address-invalid']],
            [self::ADDRESS_MODE_COUNTRY_NOT_IN_SALES_CHANNEL, self::ADDRESS_MODE_VALID, ['billing-address-blocked']],
            [self::ADDRESS_MODE_COUNTRY_NOT_SHIPPABLE, self::ADDRESS_MODE_VALID, []],
            [self::ADDRESS_MODE_COUNTRY_INACTIVE, self::ADDRESS_MODE_VALID, ['billing-address-blocked']],
            [self::ADDRESS_MODE_VALIDATION_ERROR, self::ADDRESS_MODE_VALID, ['billing-address-invalid']],
            [self::ADDRESS_MODE_VALIDATION_ERROR, self::ADDRESS_MODE_SHIPPING_SAME_AS_BILLING, ['billing-address-invalid']],
            [self::ADDRESS_MODE_COUNTRY_INACTIVE, self::ADDRESS_MODE_SHIPPING_SAME_AS_BILLING, ['billing-address-blocked', 'shipping-address-blocked']],
            [self::ADDRESS_MODE_COUNTRY_NOT_IN_SALES_CHANNEL, self::ADDRESS_MODE_VALIDATION_ERROR, ['billing-address-blocked', 'shipping-address-invalid']],
        ];
    }

    private function createCustomer(int $billingAddressMode, int $shippingAddressMode): string
    {
        $customerId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $billingCountryId = $this->getCountryId($billingAddressMode, 0, $context);
        $billingAddress = $this->createCustomerAddress($customerId, $billingCountryId);

        $shippingCountryId = $this->getCountryId($shippingAddressMode, 1, $context);
        $shippingAddress = $shippingAddressMode === self::ADDRESS_MODE_SHIPPING_SAME_AS_BILLING
            ? ['id' => $billingAddress['id']]
            : $this->createCustomerAddress($customerId, $shippingCountryId);

        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        $customerRepository->create([
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => $shippingAddress,
                'defaultBillingAddress' => $billingAddress,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'test@test.com',
                'password' => 'password',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], $context);

        return $customerId;
    }

    private function createCustomerAddress(string $customerId, string $countryId): array
    {
        $addressId = Uuid::randomHex();

        return [
            'id' => $addressId,
            'customerId' => $customerId,
            'firstName' => 'Test',
            'lastName' => 'User',
            'street' => 'Musterstraße 2',
            'city' => 'Cologne',
            'zipcode' => '89563',
            'salutationId' => $this->getValidSalutationId(),
            'countryId' => $countryId,
        ];
    }

    private function getCountryId(int $addressMode, int $offset, Context $context): string
    {
        $criteria = (new Criteria())->setLimit(1)->setOffset($offset);

        $filter = new EqualsFilter('salesChannels.id', Defaults::SALES_CHANNEL);
        if ($addressMode !== self::ADDRESS_MODE_COUNTRY_NOT_IN_SALES_CHANNEL) {
            $criteria->addFilter($filter);
        } else {
            $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_OR, [$filter]));
        }

        /** @var EntityRepositoryInterface $countryRepository */
        $countryRepository = $this->getContainer()->get('country.repository');

        $id = $countryRepository->searchIds($criteria, $context)->firstId();
        static::assertNotNull($id);

        $update = [
            'id' => $id,
            'active' => true,
            'shippingAvailable' => true,
        ];
        switch ($addressMode) {
            case self::ADDRESS_MODE_COUNTRY_INACTIVE:
                $update['active'] = false;

                break;
            case self::ADDRESS_MODE_COUNTRY_NOT_SHIPPABLE:
                $update['shippingAvailable'] = false;

                break;
        }
        $countryRepository->update([$update], $context);

        return $id;
    }

    private function createSalesChannelContext(
        string $customerId,
        int $billingAddressMode,
        int $shippingAddressMode
    ): SalesChannelContext {
        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create(
            Uuid::randomHex(),
            Defaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
            ]
        );

        if ($shippingAddressMode === self::ADDRESS_MODE_VALIDATION_ERROR) {
            $salesChannelContext->getShippingLocation()->getAddress()->setLastName('');
            $salesChannelContext->getCustomer()->getActiveShippingAddress()->setLastName('');
        }
        if ($billingAddressMode === self::ADDRESS_MODE_VALIDATION_ERROR) {
            $salesChannelContext->getCustomer()->getActiveBillingAddress()->setLastName('');
        }

        return $salesChannelContext;
    }
}
