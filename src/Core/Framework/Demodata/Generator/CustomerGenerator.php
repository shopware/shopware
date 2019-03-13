<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

class CustomerGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerGroupRepository;
    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $numberRangeValueGenerator;

    public function __construct(
        EntityWriterInterface $writer,
        EntityRepositoryInterface $customerGroupRepository,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator)
    {
        $this->writer = $writer;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
    }

    public function getDefinition(): string
    {
        return CustomerDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->createCustomers($numberOfItems, $context);

        try {
            $this->createDefaultCustomer($context->getContext());
        } catch (\Exception $e) {
            $context->getConsole()->warning('Could not create default customer: ' . $e->getMessage());
        }
    }

    private function createNetCustomerGroup(Context $context): string
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'displayGross' => false,
            'inputGross' => false,
            'hasGlobalDiscount' => false,
            'name' => 'Net price customer group',
        ];

        $this->customerGroupRepository->create([$data], $context);

        return $id;
    }

    private function createDefaultCustomer(Context $context): void
    {
        $id = Uuid::uuid4()->getHex();
        $shippingAddressId = Uuid::uuid4()->getHex();
        $billingAddressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $id,
            'customerNumber' => '1337',
            'salutation' => 'Herr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $billingAddressId,
            'defaultShippingAddressId' => $shippingAddressId,
            'addresses' => [
                [
                    'id' => $shippingAddressId,
                    'customerId' => $id,
                    'countryId' => 'ffe61e1c99154f9597014a310ab5482d',
                    'salutation' => 'Herr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
                [
                    'id' => $billingAddressId,
                    'customerId' => $id,
                    'countryId' => 'ffe61e1c99154f9597014a310ab5482d',
                    'salutation' => 'Herr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Bahnhofstraße 27',
                    'zipcode' => '10332',
                    'city' => 'Berlin',
                ],
            ],
        ];

        $writeContext = WriteContext::createFromContext($context);

        $this->writer->upsert(CustomerDefinition::class, [$customer], $writeContext);
    }

    private function createCustomers(int $numberOfItems, DemodataContext $context): void
    {
        $number = $context->getFaker()->randomNumber();
        $writeContext = WriteContext::createFromContext($context->getContext());

        $context->getConsole()->progressStart($numberOfItems);

        $netCustomerGroupId = $this->createNetCustomerGroup($context->getContext());
        $customerGroups = [Defaults::FALLBACK_CUSTOMER_GROUP, $netCustomerGroupId];

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $id = Uuid::uuid4()->getHex();
            $firstName = $context->getFaker()->firstName;
            $lastName = $context->getFaker()->lastName;
            $salutation = $context->getFaker()->title;
            $countries = [Defaults::COUNTRY, 'ffe61e1c99154f9597014a310ab5482d'];

            $addresses = [];

            $aCount = random_int(2, 5);
            for ($x = 1; $x < $aCount; ++$x) {
                $addresses[] = [
                    'id' => Uuid::uuid4()->getHex(),
                    'countryId' => $countries[array_rand($countries)],
                    'salutation' => $salutation,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'street' => $context->getFaker()->streetName,
                    'zipcode' => $context->getFaker()->postcode,
                    'city' => $context->getFaker()->city,
                ];
            }

            $customer = [
                'id' => $id,
                'customerNumber' => $this->numberRangeValueGenerator->getValue('customer', $context->getContext(), null),
                'salutation' => $salutation,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $id . $context->getFaker()->safeEmail,
                'password' => 'shopware',
                'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
                'groupId' => $customerGroups[array_rand($customerGroups)],
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultBillingAddressId' => $addresses[array_rand($addresses)]['id'],
                'defaultShippingAddressId' => $addresses[array_rand($addresses)]['id'],
                'addresses' => $addresses,
            ];

            $payload[] = $customer;

            if (\count($payload) >= 100) {
                $this->writer->upsert(CustomerDefinition::class, $payload, $writeContext);

                $context->getConsole()->progressAdvance(\count($payload));
                $context->add(CustomerDefinition::class, ...array_column($payload, 'id'));

                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->writer->upsert(CustomerDefinition::class, $payload, $writeContext);

            $context->getConsole()->progressAdvance(\count($payload));
            $context->add(CustomerDefinition::class, ...array_column($payload, 'id'));
        }

        $context->getConsole()->progressFinish();
    }
}
