<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('core')]
class CustomerGenerator implements DemodataGeneratorInterface
{
    /**
     * @var list<string>
     */
    private array $salutationIds = [];

    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly Connection $connection,
        private readonly EntityRepository $customerGroupRepository,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly CustomerDefinition $customerDefinition
    ) {
    }

    public function getDefinition(): string
    {
        return CustomerDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();
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
            'name' => 'Net price customer group',
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
        $countries = $this->connection->fetchFirstColumn('SELECT id FROM country WHERE active = 1');
        $salesChannelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');

        $customer = [
            'id' => $id,
            'customerNumber' => '1337',
            'salutationId' => $salutationId,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getDefaultPaymentMethod(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => $salesChannelIds[array_rand($salesChannelIds)],
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
        $customerGroups = [TestDefaults::FALLBACK_CUSTOMER_GROUP, $netCustomerGroupId];
        $tags = $this->getIds('tag');

        $salesChannelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');

        $payload = [];
        for ($i = 0; $i < $numberOfItems; ++$i) {
            $id = Uuid::randomHex();
            $firstName = $context->getFaker()->firstName();
            $lastName = $context->getFaker()->format('lastName');
            $salutationId = Uuid::fromBytesToHex($this->getRandomSalutationId());
            $title = $this->getRandomTitle();
            $countries = $this->connection->fetchFirstColumn('SELECT id FROM country WHERE active = 1');

            $addresses = [];

            $aCount = random_int(2, 5);
            for ($x = 1; $x < $aCount; ++$x) {
                $addresses[] = [
                    'id' => Uuid::randomHex(),
                    'countryId' => Uuid::fromBytesToHex($context->getFaker()->randomElement($countries)),
                    'salutationId' => $salutationId,
                    'title' => $title,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'street' => $context->getFaker()->format('streetName'),
                    'zipcode' => $context->getFaker()->format('postcode'),
                    'city' => $context->getFaker()->format('city'),
                ];
            }

            $customer = [
                'id' => $id,
                'customerNumber' => $this->numberRangeValueGenerator->getValue('customer', $context->getContext(), null),
                'salutationId' => $salutationId,
                'title' => $title,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $id . $context->getFaker()->format('safeEmail'),
                'password' => 'shopware',
                'defaultPaymentMethodId' => $this->getDefaultPaymentMethod(),
                'groupId' => $customerGroups[array_rand($customerGroups)],
                'salesChannelId' => $salesChannelIds[array_rand($salesChannelIds)],
                'defaultBillingAddressId' => $addresses[array_rand($addresses)]['id'],
                'defaultShippingAddressId' => $addresses[array_rand($addresses)]['id'],
                'addresses' => $addresses,
                'tags' => $this->getTags($tags),
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

    /**
     * @param list<string> $tags
     *
     * @return array<array{id: string}>
     */
    private function getTags(array $tags): array
    {
        $tagAssignments = [];

        if (!empty($tags)) {
            $chosenTags = $this->faker->randomElements($tags, $this->faker->randomDigit(), false);

            if (!empty($chosenTags)) {
                $tagAssignments = array_map(
                    fn ($id) => ['id' => $id],
                    $chosenTags
                );
            }
        }

        return $tagAssignments;
    }

    /**
     * @return list<string>
     */
    private function getIds(string $table): array
    {
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM ' . $table . ' LIMIT 500');
    }

    private function getRandomSalutationId(): string
    {
        if (!$this->salutationIds) {
            $this->salutationIds = $this->connection->fetchFirstColumn('SELECT id FROM salutation');
        }

        return $this->salutationIds[array_rand($this->salutationIds)];
    }

    private function getDefaultPaymentMethod(): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT `id` FROM `payment_method` WHERE `active` = 1 ORDER BY `position` ASC'
        );

        if (!$id) {
            return null;
        }

        return Uuid::fromBytesToHex($id);
    }
}
