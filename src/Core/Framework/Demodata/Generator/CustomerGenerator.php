<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;
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

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array
     */
    private $salutationIds;

    /**
     * @var CustomerDefinition
     */
    private $customerDefinition;

    public function __construct(
        EntityWriterInterface $writer,
        Connection $connection,
        EntityRepositoryInterface $customerGroupRepository,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        CustomerDefinition $customerDefinition
    ) {
        $this->writer = $writer;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->connection = $connection;
        $this->customerDefinition = $customerDefinition;
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
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'displayGross' => false,
            'translations' => [
                'en-GB' => [
                    'name' => 'Net price customer group',
                ],
                'de-DE' => [
                    'name' => 'Nettopreis-Kundengruppe',
                ],
            ],
        ];

        $this->customerGroupRepository->create([$data], $context);

        return $id;
    }

    private function createDefaultCustomer(Context $context): void
    {
        $id = Uuid::randomHex();
        $shippingAddressId = Uuid::randomHex();
        $billingAddressId = Uuid::randomHex();
        $salutationId = Uuid::fromBytesToHex($this->getRandomSalutationId());
        $countries = $this->connection
            ->executeQuery('SELECT id FROM country WHERE active = 1')
            ->fetchAll(FetchMode::COLUMN);

        $customer = [
            'id' => $id,
            'customerNumber' => '1337',
            'salutationId' => $salutationId,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getDefaultPaymentMethod(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $billingAddressId,
            'defaultShippingAddressId' => $shippingAddressId,
            'addresses' => [
                [
                    'id' => $shippingAddressId,
                    'customerId' => $id,
                    'countryId' => Uuid::fromBytesToHex($countries[array_rand($countries)]),
                    'salutationId' => $salutationId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
                [
                    'id' => $billingAddressId,
                    'customerId' => $id,
                    'countryId' => Uuid::fromBytesToHex($countries[array_rand($countries)]),
                    'salutationId' => $salutationId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Bahnhofstraße 27',
                    'zipcode' => '10332',
                    'city' => 'Berlin',
                ],
            ],
        ];

        $writeContext = WriteContext::createFromContext($context);

        $this->writer->upsert($this->customerDefinition, [$customer], $writeContext);
    }

    private function createCustomers(int $numberOfItems, DemodataContext $context): void
    {
        $writeContext = WriteContext::createFromContext($context->getContext());

        $context->getConsole()->progressStart($numberOfItems);

        $netCustomerGroupId = $this->createNetCustomerGroup($context->getContext());
        $customerGroups = [Defaults::FALLBACK_CUSTOMER_GROUP, $netCustomerGroupId];

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $id = Uuid::randomHex();
            $firstName = $context->getFaker()->firstName;
            $lastName = $context->getFaker()->lastName;
            $salutationId = Uuid::fromBytesToHex($this->getRandomSalutationId());
            $title = $this->getRandomTitle();
            $countries = $this->connection
                ->executeQuery('SELECT id FROM country WHERE active = 1')
                ->fetchAll(FetchMode::COLUMN);

            $addresses = [];

            $aCount = random_int(2, 5);
            for ($x = 1; $x < $aCount; ++$x) {
                $addresses[] = [
                    'id' => Uuid::randomHex(),
                    'countryId' => Uuid::fromBytesToHex($countries[array_rand($countries)]),
                    'salutationId' => $salutationId,
                    'title' => $title,
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
                'salutationId' => $salutationId,
                'title' => $title,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $id . $context->getFaker()->safeEmail,
                'password' => 'shopware',
                'defaultPaymentMethodId' => $this->getDefaultPaymentMethod(),
                'groupId' => $customerGroups[array_rand($customerGroups)],
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultBillingAddressId' => $addresses[array_rand($addresses)]['id'],
                'defaultShippingAddressId' => $addresses[array_rand($addresses)]['id'],
                'addresses' => $addresses,
            ];

            $payload[] = $customer;

            if (\count($payload) >= 100) {
                $this->writer->upsert($this->customerDefinition, $payload, $writeContext);

                $context->getConsole()->progressAdvance(\count($payload));

                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->writer->upsert($this->customerDefinition, $payload, $writeContext);

            $context->getConsole()->progressAdvance(\count($payload));
        }

        $context->getConsole()->progressFinish();
    }

    private function getRandomTitle(): string
    {
        $titles = ['', 'Dr.', 'Dr. med.', 'Prof.', 'Prof. Dr.'];

        return $titles[array_rand($titles)];
    }

    private function getRandomSalutationId(): string
    {
        if (!$this->salutationIds) {
            $this->salutationIds = $this->connection->executeQuery('SELECT id FROM salutation')->fetchAll(FetchMode::COLUMN);
        }

        return $this->salutationIds[array_rand($this->salutationIds)];
    }

    private function getDefaultPaymentMethod(): ?string
    {
        $id = $this->connection->executeQuery(
            'SELECT `id` FROM `payment_method` WHERE `active` = 1 ORDER BY `position` ASC'
        )->fetchColumn();

        if (!$id) {
            return null;
        }

        return Uuid::fromBytesToHex($id);
    }
}
