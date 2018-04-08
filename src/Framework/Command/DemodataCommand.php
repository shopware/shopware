<?php declare(strict_types=1);

namespace Shopware\Framework\Command;

use Bezhanov\Faker\Provider\Commerce;
use Faker\Factory;
use Faker\Generator;
use Shopware\Api\Category\Definition\CategoryDefinition;
use Shopware\Api\Configuration\Definition\ConfigurationGroupDefinition;
use Shopware\Api\Context\Definition\ContextRuleDefinition;
use Shopware\Api\Customer\Definition\CustomerDefinition;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Definition\ProductManufacturerDefinition;
use Shopware\Context\Rule\Container\AndRule;
use Shopware\Context\Rule\Container\NotRule;
use Shopware\Context\Rule\CurrencyRule;
use Shopware\Context\Rule\CustomerGroupRule;
use Shopware\Context\Rule\DateRangeRule;
use Shopware\Context\Rule\GoodsPriceRule;
use Shopware\Context\Rule\IsNewCustomerRule;
use Shopware\Context\Rule\ShopRule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Shopware\Product\Service\VariantGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DemodataCommand extends ContainerAwareCommand
{
    /**
     * @var Generator
     */
    private $faker;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var array
     */
    private $categories = [];

    /**
     * @var VariantGenerator
     */
    private $variantGenerator;

    public function __construct(
        ?string $name = null,
        EntityWriterInterface $writer,
        VariantGenerator $variantGenerator
    ) {
        parent::__construct($name);
        $this->writer = $writer;
        $this->variantGenerator = $variantGenerator;
    }

    protected function configure()
    {
        $this->addOption('products', 'p', InputOption::VALUE_REQUIRED, 'Product count', 500);
        $this->addOption('categories', 'c', InputOption::VALUE_REQUIRED, 'Category count', 10);
        $this->addOption('manufacturers', 'm', InputOption::VALUE_REQUIRED, 'Manufacturer count', 50);
        $this->addOption('customers', null, InputOption::VALUE_REQUIRED, 'Customer count', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->faker = Factory::create('de_DE');
        $this->faker->addProvider(new Commerce($this->faker));

        $this->io->title('Demodata Generator');

        $contextRuleIds = $this->createContextRules();

        $this->createCustomer($input->getOption('customers'));

        $this->createDefaultCustomer();

        $categories = $this->createCategory($input->getOption('categories'));

        $manufacturer = $this->createManufacturer($input->getOption('manufacturers'));

        $this->createProduct(
            $categories,
            $manufacturer,
            $contextRuleIds,
            $input->getOption('products')
        );

        $this->io->newLine();

        $this->io->success('Successfully created demodata.');
    }

    private function getContext()
    {
        return WriteContext::createFromShopContext(
            ShopContext::createDefaultContext()
        );
    }

    private function createCategory($count = 10)
    {
        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $payload[] = [
                'id' => Uuid::uuid4()->getHex(),
                'name' => $this->randomDepartment(),
                'parentId' => 'a1abd0ee-0aa6-4fcd-aef7-25b8b84e5943',
            ];
        }
        $parents = $payload;
        foreach ($parents as $category) {
            for ($x = 0; $x < 40; ++$x) {
                $payload[] = [
                    'id' => Uuid::uuid4()->getHex(),
                    'name' => $this->randomDepartment(),
                    'parentId' => $category['id'],
                ];
            }
        }

        $count = count($payload);
        $this->io->section("Generating {$count} categories...");
        $this->io->progressStart($count);

        $chunks = array_chunk($payload, 100);
        foreach ($chunks as $chunk) {
            $this->writer->upsert(CategoryDefinition::class, $chunk, $this->getContext());
            $this->io->progressAdvance(count($chunk));
        }

        $this->io->progressFinish();
        $this->io->comment('Writing to database...');

        return array_column($payload, 'id');
    }

    private function createCustomer($count = 500)
    {
        $number = $this->faker->randomNumber;
        $password = password_hash('shopware', PASSWORD_BCRYPT, ['cost' => 13]);

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $id = Uuid::uuid4()->getHex();
            $addressId = Uuid::uuid4()->getHex();
            $firstName = $this->faker->firstName;
            $lastName = $this->faker->lastName;
            $salutation = $this->faker->title;

            $customer = [
                'id' => $id,
                'number' => (string) ($number + $i),
                'salutation' => $salutation,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $this->faker->safeEmail,
                'password' => $password,
                'defaultPaymentMethodId' => '47160b00-cd06-4b01-8817-6451f9f3c247',
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'shopId' => Defaults::SHOP,
                'defaultBillingAddressId' => $addressId,
                'defaultShippingAddressId' => $addressId,
                'addresses' => [
                    [
                        'id' => $addressId,
                        'customerId' => $id,
                        'countryId' => 'ffe61e1c-9915-4f95-9701-4a310ab5482d',
                        'salutation' => $salutation,
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'street' => $this->faker->streetName,
                        'zipcode' => $this->faker->postcode,
                        'city' => $this->faker->city,
                    ],
                ],
            ];

            $payload[] = $customer;
        }

        $this->io->section(sprintf('Generating %d customers...', count($payload)));
        $this->io->progressStart(count($payload));

        $chunks = array_chunk($payload, 150);
        foreach ($chunks as $chunk) {
            $this->writer->upsert(CustomerDefinition::class, $chunk, $this->getContext());
            $this->io->progressAdvance(count($chunk));
        }

        $this->io->progressFinish();
        $this->io->comment('Writing to database...');
    }

    private function createDefaultCustomer()
    {
        $id = Uuid::uuid4()->getHex();
        $shippingAddressId = Uuid::uuid4()->getHex();
        $billingAddressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $id,
            'number' => '1337',
            'salutation' => 'Herr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => password_hash('shopware', PASSWORD_BCRYPT, ['cost' => 13]),
            'defaultPaymentMethodId' => '47160b00cd064b0188176451f9f3c247',
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'shopId' => Defaults::SHOP,
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

        $this->writer->upsert(CustomerDefinition::class, [$customer], $this->getContext());
    }

    private function createProduct(array $categories, array $manufacturer, array $contextRules, $count = 500)
    {
        $payload = [];

        $size = 100;
        if ($size > $count) {
            $size = $count;
        }

        $this->io->section(sprintf('Generating %d products...', $count));
        $this->io->progressStart($count);

        $configurator = $this->createConfigurators();

        for ($i = 0; $i < $count; ++$i) {
            if ($i % 10 === 0) {
                $this->createConfiguratorProduct($categories, $manufacturer, $contextRules, $configurator);
            } else {
                $payload[] = $this->createSimpleProduct($categories, $manufacturer, $contextRules);
            }
            if ($i % $size === 0 && $i > 0) {
                $this->writer->upsert(ProductDefinition::class, $payload, $this->getContext());
                $this->io->progressAdvance(count($payload) + 10);
                $payload = [];
            }
        }

        $this->io->progressFinish();
    }

    private function createManufacturer($count = 50)
    {
        $this->io->section("Generating {$count} manufacturer...");
        $this->io->progressStart($count);

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $payload[] = [
                'id' => Uuid::uuid4()->getHex(),
                'name' => $this->faker->company,
                'link' => $this->faker->url,
            ];
        }

        $chunks = array_chunk($payload, 100);

        foreach ($chunks as $chunk) {
            $this->writer->upsert(ProductManufacturerDefinition::class, $chunk, $this->getContext());
            $this->io->progressAdvance(count($chunk));
        }

        $this->io->progressFinish();

        return array_column($payload, 'id');
    }

    private function createContextRules(): array
    {
        $pool = [
            new IsNewCustomerRule(),
            new DateRangeRule(new \DateTime(), (new \DateTime())->modify('+2 day')),
            new GoodsPriceRule(5000, GoodsPriceRule::OPERATOR_GTE),
            new ShopRule([Defaults::SHOP], ShopRule::OPERATOR_NEQ),
            new NotRule([new CustomerGroupRule([Defaults::FALLBACK_CUSTOMER_GROUP])]),
            new NotRule([new CurrencyRule([Defaults::CURRENCY])]),
        ];

        $payload = [];
        for ($i = 0; $i < 50; ++$i) {
            $rules = \array_slice($pool, random_int(0, count($pool) - 2), random_int(1, 2));

            $payload[] = [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'High cart value',
                'priority' => $i,
                'payload' => new AndRule($rules),
            ];
        }

        $this->writer->insert(ContextRuleDefinition::class, $payload, $this->getContext());

        return array_column($payload, 'id');
    }

    private function createPrices(array $contextRules)
    {
        $prices = [];
        $rules = \array_slice(
            $contextRules,
            random_int(0, count($contextRules) - 5),
            random_int(1, 5)
        );

        foreach ($rules as $ruleId) {
            $gross = random_int(500, 1000);

            $prices[] = [
                'currencyId' => Defaults::CURRENCY,
                'contextRuleId' => $ruleId,
                'quantityStart' => 1,
                'quantityEnd' => 10,
                'price' => ['gross' => $gross, 'net' => $gross / 1.19],
            ];

            $gross = random_int(1, 499);

            $prices[] = [
                'currencyId' => Defaults::CURRENCY,
                'contextRuleId' => $ruleId,
                'quantityStart' => 1,
                'price' => ['gross' => $gross, 'net' => $gross / 1.19],
            ];
        }

        return $prices;
    }

    private function randomDepartment(int $max = 3, bool $fixedAmount = false, bool $unique = true)
    {
        if (!$fixedAmount) {
            $max = mt_rand(1, $max);
        }
        do {
            $categories = [];

            while (count($categories) < $max) {
                $category = $this->faker->category();
                if (!in_array($category, $categories)) {
                    $categories[] = $category;
                }
            }

            if (count($categories) >= 2) {
                $commaSeparatedCategories = implode(', ', array_slice($categories, 0, -1));
                $categories = [
                    $commaSeparatedCategories,
                    end($categories),
                ];
            }
            ++$max;
            $categoryName = implode(' & ', $categories);
        } while (in_array($categoryName, $this->categories) && $unique);
        $this->categories[] = $categoryName;

        return $categoryName;
    }

    /**
     * @param array $categories
     * @param array $manufacturer
     * @param array $contextRules
     *
     * @return array
     */
    private function createSimpleProduct(array $categories, array $manufacturer, array $contextRules): array
    {
        $price = mt_rand(1, 1000);

        $product = [
            'id' => Uuid::uuid4()->getHex(),
            'price' => ['gross' => $price, 'net' => $price / 1.19],
            'name' => $this->faker->productName,
            'description' => $this->faker->text(),
            'descriptionLong' => $this->faker->randomHtml(2, 3),
            'taxId' => '4926035368e34d9fa695e017d7a231b9',
            'manufacturerId' => $manufacturer[random_int(0, count($manufacturer) - 1)],
            'active' => true,
            'categories' => [
                ['id' => $categories[random_int(0, count($categories) - 1)]],
            ],
            'stock' => $this->faker->randomNumber(),
            'contextPrices' => $this->createPrices($contextRules),
        ];

        return $product;
    }

    private function createConfiguratorProduct(
        array $categories,
        array $manufacturer,
        array $contextRules,
        array $configurator
    ) {
        $product = $this->createSimpleProduct($categories, $manufacturer, $contextRules);

        $groups = array_slice($configurator, 0, random_int(1, 2));

        $optionIds = [];
        foreach ($groups as $group) {
            $ids = array_column($group['options'], 'id');

            $count = random_int(2, count($ids));

            $offset = random_int(0, count($ids) / 3);

            $optionIds = array_merge(
                $ids,
                array_slice($ids, $offset, $count)
            );
        }

        $options = array_map(function ($id) {
            $price = random_int(2, 10);

            return [
                'optionId' => $id,
                'price' => ['gross' => $price, 'net' => $price / 1.19],
            ];
        }, $optionIds);

        $product['configurators'] = $options;

        $this->writer->insert(ProductDefinition::class, [$product], $this->getContext());

        $this->variantGenerator->generate($product['id'], ShopContext::createDefaultContext());
    }

    private function createConfigurators()
    {
        $data = [
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'color',
                'options' => [
                    ['id' => Uuid::uuid4()->getHex(), 'name' => 'red'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => 'blue'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => 'green'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => 'black'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => 'white'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => 'coral'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => 'brown'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => 'orange'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => 'violet'],
                ],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'size',
                'options' => [
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '32'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '34'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '36'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '38'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '40'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '42'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '44'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '46'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '48'],
                ],
            ],
        ];

        $this->writer->insert(ConfigurationGroupDefinition::class, $data, $this->getContext());

        return $data;
    }
}
