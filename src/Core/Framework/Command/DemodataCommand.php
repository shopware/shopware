<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use bheller\ImagesGenerator\ImagesGeneratorProvider;
use DateTime;
use Doctrine\DBAL\Connection;
use Exception;
use Faker\Factory;
use Faker\Generator;
use function Flag\next1207;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule;
use Shopware\Core\Checkout\Customer\Rule\IsNewCustomerRule;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Configuration\ConfigurationGroupDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Util\VariantGenerator;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Faker\Commerce;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\CurrencyRule;
use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Util\Random;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use function Flag\next739;

class DemodataCommand extends Command
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

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * Images to be deleted after generating data
     *
     * @var string[]
     */
    private $tmpImages = [];

    /**
     * @var OrderConverter
     */
    private $orderConverter;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CheckoutContextFactory
     */
    private $contextFactory;

    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var FileSaver
     */
    private $mediaUpdater;

    /**
     * @var string
     */
    private $kernelEnv;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var EntityRepositoryInterface
     */
    private $configurationGroupRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $defaultFolderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productStreamRepository;

    /**
     * @var FileNameProvider
     */
    private $fileNameProvider;

    public function __construct(
        EntityWriterInterface $writer,
        VariantGenerator $variantGenerator,
        OrderConverter $orderConverter,
        Connection $connection,
        CheckoutContextFactory $contextFactory,
        Processor $calculator,
        FileSaver $mediaUpdater,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $ruleRepository,
        EntityRepositoryInterface $categoryRepository,
        EntityRepositoryInterface $taxRepository,
        EntityRepositoryInterface $defaultFolderRepository,
        EntityRepositoryInterface $configurationGroupRepository,
        EntityRepositoryInterface $productStreamRepository,
        FileNameProvider $fileNameProvider,
        string $kernelEnv,
        string $projectDir
    ) {
        parent::__construct();
        $this->writer = $writer;

        $this->variantGenerator = $variantGenerator;
        $this->productRepository = $productRepository;
        $this->ruleRepository = $ruleRepository;
        $this->categoryRepository = $categoryRepository;
        $this->taxRepository = $taxRepository;
        $this->orderConverter = $orderConverter;
        $this->connection = $connection;
        $this->contextFactory = $contextFactory;
        $this->processor = $calculator;
        $this->mediaUpdater = $mediaUpdater;
        $this->kernelEnv = $kernelEnv;
        $this->projectDir = $projectDir;
        $this->configurationGroupRepository = $configurationGroupRepository;
        $this->defaultFolderRepository = $defaultFolderRepository;
        $this->productStreamRepository = $productStreamRepository;
        $this->fileNameProvider = $fileNameProvider;
    }

    protected function configure(): void
    {
        $this->setName('framework:demodata');
        $this->addOption('products', 'p', InputOption::VALUE_REQUIRED, 'Product count', 500);
        $this->addOption('categories', 'c', InputOption::VALUE_REQUIRED, 'Category count', 10);
        $this->addOption('orders', 'o', InputOption::VALUE_REQUIRED, 'Order count', 50);
        $this->addOption('manufacturers', 'm', InputOption::VALUE_REQUIRED, 'Manufacturer count', 50);
        $this->addOption('customers', 'cs', InputOption::VALUE_REQUIRED, 'Customer count', 200);
        $this->addOption('media', '', InputOption::VALUE_REQUIRED, 'Media count', 100);
        $this->addOption('properties', '', InputOption::VALUE_REQUIRED, 'Property group count (option count rand(30-300)', 10);

        if (next739()) {
            $this->addOption('product-streams', 'ps', InputOption::VALUE_REQUIRED, 'Product streams count', 10);
        }

        $this->addOption('with-configurator', 'w', InputOption::VALUE_OPTIONAL, 'Enables configurator products', 0);
        $this->addOption('with-services', 'x', InputOption::VALUE_OPTIONAL, 'Enables services for products', 1);
        $this->addOption('with-media', 'y', InputOption::VALUE_OPTIONAL, 'Enables media for products', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->kernelEnv !== 'prod') {
            $output->writeln('Demo data command should only be used in production environment. You can provide the environment as follow `APP_ENV=prod framework:demodata`');

            return;
        }

        $this->io = new SymfonyStyle($input, $output);
        $this->faker = Factory::create('de_DE');
        $this->faker->addProvider(new Commerce($this->faker));
        $this->faker->addProvider(new ImagesGeneratorProvider($this->faker));

        $this->io->title('Demodata Generator');

        $ruleIds = $this->createRules();

        $this->createCustomer($input->getOption('customers'));

        try {
            $this->createDefaultCustomer();
        } catch (Exception $e) {
            $this->io->warning('Could not create default customer: ' . $e->getMessage());
        }

        $this->createProperties((int) $input->getOption('properties'));

        $categories = $this->createCategory((int) $input->getOption('categories'));

        $manufacturer = $this->createManufacturer($input->getOption('manufacturers'));

        $products = $this->createProduct(
            $categories,
            $manufacturer,
            $ruleIds,
            $input->getOption('products'),
            (int) $input->getOption('with-media') === 1,
            (int) $input->getOption('with-configurator') === 1,
            (int) $input->getOption('with-services') === 1
        );

        if (next739()) {
            $this->createProductStreams((int) $input->getOption('product-streams'), $categories, $manufacturer, $products);
        }

        $this->createOrders((int) $input->getOption('orders'));

        $this->createMedia((int) $input->getOption('media'));

        $this->cleanupImages();

        $this->io->newLine();

        $this->io->success('Successfully created demodata.');
    }

    private function getContext(): WriteContext
    {
        return WriteContext::createFromContext(
            Context::createDefaultContext()
        );
    }

    private function createCategory(int $count = 10)
    {
        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $payload[] = [
                'id' => Uuid::uuid4()->getHex(),
                'catalogId' => Defaults::CATALOG,
                'name' => $this->randomDepartment(),
                'position' => $i,
            ];
        }

        foreach ($payload as $category) {
            for ($x = 0; $x < 40; ++$x) {
                $payload[] = [
                    'id' => Uuid::uuid4()->getHex(),
                    'catalogId' => Defaults::CATALOG,
                    'name' => $this->randomDepartment(),
                    'parentId' => $category['id'],
                    'position' => $x,
                ];
            }
        }

        $count = \count($payload);
        $this->io->section("Generating {$count} categories...");
        $this->io->progressStart($count);

        foreach (array_chunk($payload, 100) as $chunk) {
            $this->categoryRepository->upsert($chunk, Context::createDefaultContext());
            $this->io->progressAdvance(\count($chunk));
        }

        $this->io->progressFinish();
        $this->io->comment('Writing to database...');

        return array_column($payload, 'id');
    }

    private function createCustomer($count = 500): void
    {
        $number = $this->faker->randomNumber();

        $this->io->section(sprintf('Generating %d customers...', $count));
        $this->io->progressStart($count);

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $id = Uuid::uuid4()->getHex();
            $addressId = Uuid::uuid4()->getHex();
            $firstName = $this->faker->firstName;
            $lastName = $this->faker->lastName;
            $salutation = $this->faker->title;

            $addresses = [
                [
                    'id' => $addressId,
                    'countryId' => 'ffe61e1c-9915-4f95-9701-4a310ab5482d',
                    'salutation' => $salutation,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'street' => $this->faker->streetName,
                    'zipcode' => $this->faker->postcode,
                    'city' => $this->faker->city,
                ],
            ];

            $aCount = random_int(2, 5);
            for ($x = 1; $x < $aCount; ++$x) {
                $addresses[] = [
                    'countryId' => Defaults::COUNTRY,
                    'salutation' => $salutation,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'street' => $this->faker->streetName,
                    'zipcode' => $this->faker->postcode,
                    'city' => $this->faker->city,
                ];
            }

            $customer = [
                'id' => $id,
                'customerNumber' => (string) ($number + $i),
                'salutation' => $salutation,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $id . $this->faker->safeEmail,
                'password' => 'shopware',
                'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultBillingAddressId' => $addressId,
                'defaultShippingAddressId' => $addressId,
                'addresses' => $addresses,
            ];

            $payload[] = $customer;

            if (\count($payload) >= 100) {
                $this->writer->upsert(CustomerDefinition::class, $payload, $this->getContext());
                $this->io->progressAdvance(\count($payload));
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->writer->upsert(CustomerDefinition::class, $payload, $this->getContext());
            $this->io->progressAdvance(\count($payload));
        }

        $this->io->progressFinish();
        $this->io->comment('Writing to database...');
    }

    private function createDefaultCustomer(): void
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

        $this->writer->upsert(CustomerDefinition::class, [$customer], $this->getContext());
    }

    private function createProduct(
        array $categories,
        array $manufacturer,
        array $rules,
        $count = 500,
        bool $withMedia = false,
        bool $withConfigurator = false,
        bool $withServices = false
    ): array {
        $productIds = [];
        $payload = [];
        $productImages = [];

        $this->io->section(sprintf('Generating %d products...', $count));
        $this->io->progressStart($count);

        $configurator = [];
        if ($withConfigurator) {
            $configurator = $this->createConfigurators();
        }

        $services = [];
        if ($withServices) {
            $services = $this->createServices();
        }

        $context = Context::createDefaultContext();

        $importImages = function () use (&$productImages, $context) {
            foreach ($productImages as $id => $file) {
                $this->mediaUpdater->persistFileToMedia(
                    new MediaFile(
                        $file,
                        mime_content_type($file),
                        pathinfo($file, PATHINFO_EXTENSION),
                        filesize($file)
                    ),
                    $this->fileNameProvider->provide(
                        pathinfo($file, PATHINFO_FILENAME),
                        pathinfo($file, PATHINFO_EXTENSION),
                        $id,
                        $context
                    ),
                    $id,
                    $context
                );
            }

            $productImages = [];
        };

        $mediaFolderId = null;

        if (next1207()) {
            $mediaFolderId = $this->getOrCreateDefaultFolder($context);
        }

        $taxes = array_values($this->taxRepository->search(new Criteria(), $context)->getIds());

        $properties = $this->getProperties();

        for ($i = 0; $i < $count; ++$i) {
            $product = $this->createSimpleProduct($categories, $manufacturer, $rules, $taxes);

            if ($withMedia) {
                $imagePath = $this->getRandomImage($product['name']);
                $mediaId = Uuid::uuid4()->getHex();
                $product['cover'] = [
                    'media' => [
                        'id' => $mediaId,
                        'name' => 'Product image of ' . $product['name'],
                        'mediaFolderId' => $mediaFolderId,
                    ],
                ];

                $productImages[$mediaId] = $imagePath;
            }

            $hasServices = random_int(1, 100) <= 5 && $withServices;
            if ($hasServices) {
                $product['services'] = $this->buildProductServices($services, $taxes);
            }

            $isConfigurator = random_int(1, 100) <= 5 && $withConfigurator;

            if ($isConfigurator) {
                $product['configurators'] = $this->buildProductConfigurator($configurator);
            }

            $productProperties = \array_slice(
                $properties,
                random_int(0, max(0, count($properties) - 20)),
                random_int(10, 30)
            );

            $product['datasheet'] = array_map(function ($config) {
                return ['id' => $config];
            }, $productProperties);

            if ($isConfigurator) {
                $this->io->progressAdvance();

                $this->productRepository->upsert([$product], $context);

                $variantEvent = $this->variantGenerator->generate($product['id'], $context);
                $productEvents = $variantEvent->getEventByDefinition(ProductDefinition::class);
                $variantProductIds = $productEvents->getIds();

                $variantImagePayload = [];
                foreach ($variantProductIds as $y => $variantProductId) {
                    $imagePath = $this->getRandomImage($product['name'] . ' #' . $y);

                    $mediaId = Uuid::uuid4()->getHex();
                    $variantImagePayload[] = [
                        'id' => $variantProductId,
                        'cover' => [
                            'media' => [
                                'id' => $mediaId,
                                'name' => 'Product image of ' . $product['name'],
                            ],
                        ],
                    ];

                    $productImages[$mediaId] = $imagePath;
                }

                $this->productRepository->update($variantImagePayload, $context);

                continue;
            }

            $payload[] = $product;

            if (\count($payload) >= 50) {
                $this->io->progressAdvance(\count($payload));
                $this->writer->upsert(ProductDefinition::class, $payload, WriteContext::createFromContext($context));
                $productIds = array_merge($productIds, array_column($payload, 'id'));
                $importImages();
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->writer->upsert(ProductDefinition::class, $payload, WriteContext::createFromContext($context));
            $productIds = array_merge($productIds, array_column($payload, 'id'));
            $importImages();
        }

        $this->io->progressFinish();

        return $productIds;
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

        foreach (array_chunk($payload, 100) as $chunk) {
            $this->writer->upsert(ProductManufacturerDefinition::class, $chunk, $this->getContext());
            $this->io->progressAdvance(\count($chunk));
        }

        $this->io->progressFinish();

        return array_column($payload, 'id');
    }

    private function createRules(): array
    {
        $ids = $this->ruleRepository->searchIds(new Criteria(), Context::createDefaultContext());

        if (!empty($ids->getIds())) {
            return $ids->getIds();
        }

        $pool = [
            [
                'rule' => new IsNewCustomerRule(),
                'name' => 'New customer',
            ],
            [
                'rule' => (new DateRangeRule())->assign(['fromDate' => new DateTime(), 'toDate' => (new DateTime())->modify('+2 day')]),
                'name' => 'Next two days',
            ],
            [
                'rule' => (new GoodsPriceRule())->assign(['amount' => 5000, 'operator' => GoodsPriceRule::OPERATOR_GTE]),
                'name' => 'Cart >= 5000',
            ],
            [
                'rule' => (new CustomerGroupRule())->assign(['customerGroupIds' => [Defaults::FALLBACK_CUSTOMER_GROUP]]),
                'name' => 'Default group',
            ],
            [
                'rule' => (new CurrencyRule())->assign(['currencyIds' => [Defaults::CURRENCY]]),
                'name' => 'Default currency',
            ],
        ];

        $payload = [];
        for ($i = 0; $i < 20; ++$i) {
            $rules = \array_slice($pool, random_int(0, \count($pool) - 2), random_int(1, 2));

            $classes = array_column($rules, 'rule');
            $names = array_column($rules, 'name');

            $ruleData = [
                'id' => Uuid::uuid4()->getHex(),
                'priority' => $i,
                'name' => implode(' + ', $names),
                'description' => $this->faker->text(),
            ];

            $ruleData['conditions'][] = $this->buildChildRule(null, (new OrRule())->assign(['rules' => $classes]));

            $payload[] = $ruleData;
        }

        // nested condition
        $nestedRule = new OrRule();

        $nestedRuleData = [
            'id' => Uuid::uuid4()->getHex(),
            'priority' => 20,
            'name' => 'nested rule',
            'description' => $this->faker->text(),
        ];

        $this->buildNestedRule($nestedRule, $pool, 0, 6);

        $nestedRuleData['conditions'][] = $this->buildChildRule(null, $nestedRule);

        $payload[] = $nestedRuleData;

        $this->writer->insert(RuleDefinition::class, $payload, $this->getContext());

        return array_column($payload, 'id');
    }

    private function createProductStreams(int $count, array $categories, array $manufacturer, array $products): void
    {
        $this->io->section(sprintf('Generating %d product streams...', $count));
        $this->io->progressStart($count);

        $pool = [
            ['field' => 'height', 'type' => 'range', 'parameters' => [RangeFilter::GTE => rand(1, 1000)]],
            ['field' => 'width', 'type' => 'range', 'parameters' => [RangeFilter::GTE => rand(1, 1000)]],
            ['field' => 'weight', 'type' => 'range', 'parameters' => [RangeFilter::GTE => rand(1, 1000)]],
            ['field' => 'height', 'type' => 'range', 'parameters' => [RangeFilter::LTE => rand(1, 1000)]],
            ['field' => 'width', 'type' => 'range', 'parameters' => [RangeFilter::LTE => rand(1, 1000)]],
            ['field' => 'weight', 'type' => 'range', 'parameters' => [RangeFilter::LTE => rand(1, 1000)]],
            ['field' => 'height', 'type' => 'range', 'parameters' => [RangeFilter::GT => rand(1, 500), RangeFilter::LT => rand(500, 1000)]],
            ['field' => 'width', 'type' => 'range', 'parameters' => [RangeFilter::GT => rand(1, 500), RangeFilter::LT => rand(500, 1000)]],
            ['field' => 'weight', 'type' => 'range', 'parameters' => [RangeFilter::GT => rand(1, 500), RangeFilter::LT => rand(500, 1000)]],
            ['field' => 'stock', 'type' => 'equals', 'value' => '1000'],
            ['field' => 'maxDeliveryTime', 'type' => 'range', 'parameters' => [RangeFilter::LT => rand(0, 5)]],
            ['field' => 'name', 'type' => 'contains', 'value' => 'Awesome'],
            ['field' => 'categories.id', 'type' => 'equalsAny', 'value' => join('|', [$categories[random_int(0, \count($categories) - 1)], $categories[random_int(0, \count($categories) - 1)]])],
            ['field' => 'id', 'type' => 'equalsAny', 'value' => join('|', [$products[random_int(0, \count($products) - 1)], $products[random_int(0, \count($products) - 1)]])],
            ['field' => 'manufacturerId', 'type' => 'equals', 'value' => $manufacturer[random_int(0, \count($manufacturer) - 1)]],
        ];

        $pool[] = ['type' => 'multi', 'queries' => [$pool[random_int(0, \count($pool) - 1)], $pool[random_int(0, \count($pool) - 1)]]];
        $pool[] = ['type' => 'multi', 'operator' => 'OR', 'queries' => [$pool[random_int(0, \count($pool) - 1)], $pool[random_int(0, \count($pool) - 1)]]];

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $filters = [];

            for ($j = 0; $j < random_int(1, 5); ++$j) {
                $filters[] = $pool[random_int(0, \count($pool) - 1)];
            }

            $payload[] = [
                'id' => Uuid::uuid4()->getHex(),
                'priority' => $this->faker->numberBetween(0, 200),
                'name' => $this->faker->productName,
                'description' => $this->faker->text(),
                'filters' => [['type' => 'multi', 'operator' => 'OR', 'queries' => $filters]],
            ];
        }

        $this->writer->insert(ProductStreamDefinition::class, $payload, $this->getContext());

        $this->io->progressFinish();
    }

    private function buildNestedRule(Rule $rule, array $pool, int $currentDepth, int $depth): Rule
    {
        if ($currentDepth === $depth) {
            return $rule;
        }

        $rules = \array_slice($pool, random_int(0, \count($pool) - 2), random_int(1, 2));

        $classes = array_column($rules, 'rule');

        if ($currentDepth % 2 === 1) {
            $classes[] = $this->buildNestedRule(new OrRule(), $pool, $currentDepth + 1, $depth);
        } else {
            $classes[] = $this->buildNestedRule(new AndRule(), $pool, $currentDepth + 1, $depth);
        }

        $rule->assign(['rules' => $classes]);

        return $rule;
    }

    private function buildChildRule(?string $parentId, Rule $rule): array
    {
        $data = [];
        $data['value'] = $rule->jsonSerialize();
        unset($data['value']['_class'], $data['value']['rules'], $data['value']['extensions']);
        if (!$data['value']) {
            unset($data['value']);
        }
        $data['id'] = Uuid::uuid4()->getHex();
        $data['parentId'] = $parentId;
        $data['type'] = $rule->getName();

        if ($rule instanceof Container) {
            $data['children'] = [];
            foreach ($rule->getRules() as $index => $childRule) {
                $child = $this->buildChildRule($data['id'], $childRule);
                $child['position'] = $index;
                $data['children'][] = $child;
            }
        }

        return $data;
    }

    private function createPrices(array $rules): array
    {
        $prices = [];
        $rules = \array_slice(
            $rules,
            random_int(0, \count($rules) - 5),
            random_int(1, 5)
        );

        foreach ($rules as $ruleId) {
            $gross = random_int(500, 1000);

            $prices[] = [
                'currencyId' => Defaults::CURRENCY,
                'ruleId' => $ruleId,
                'quantityStart' => 1,
                'quantityEnd' => 10,
                'price' => ['gross' => $gross, 'net' => $gross / 1.19, 'linked' => true],
            ];

            $gross = random_int(1, 499);

            $prices[] = [
                'currencyId' => Defaults::CURRENCY,
                'ruleId' => $ruleId,
                'quantityStart' => 11,
                'price' => ['gross' => $gross, 'net' => $gross / 1.19, 'linked' => true],
            ];
        }

        return $prices;
    }

    private function randomDepartment(int $max = 3, bool $fixedAmount = false, bool $unique = true)
    {
        if (!$fixedAmount) {
            $max = random_int(1, $max);
        }
        do {
            $categories = [];

            while (\count($categories) < $max) {
                $category = $this->faker->category();
                if (!\in_array($category, $categories)) {
                    $categories[] = $category;
                }
            }

            if (\count($categories) >= 2) {
                $commaSeparatedCategories = implode(', ', \array_slice($categories, 0, -1));
                $categories = [
                    $commaSeparatedCategories,
                    end($categories),
                ];
            }
            ++$max;
            $categoryName = implode(' & ', $categories);
        } while (\in_array($categoryName, $this->categories) && $unique);
        $this->categories[] = $categoryName;

        return $categoryName;
    }

    /**
     * @param array $categories
     * @param array $manufacturer
     * @param array $rules
     * @param array $taxes
     *
     * @return array
     */
    private function createSimpleProduct(array $categories, array $manufacturer, array $rules, array $taxes): array
    {
        $price = random_int(1, 1000);

        $product = [
            'id' => Uuid::uuid4()->getHex(),
            'price' => ['gross' => $price, 'net' => $price / 1.19, 'linked' => true],
            'name' => $this->faker->productName,
            'description' => $this->faker->text(),
            'descriptionLong' => $this->generateRandomHTML(10, ['b', 'i', 'u', 'p', 'h1', 'h2', 'h3', 'h4', 'cite']),
            'taxId' => $taxes[random_int(0, \count($taxes) - 1)],
            'manufacturerId' => $manufacturer[random_int(0, \count($manufacturer) - 1)],
            'active' => true,
            'categories' => [
                ['id' => $categories[random_int(0, \count($categories) - 1)]],
            ],
            'stock' => $this->faker->randomNumber(),
            'priceRules' => $this->createPrices($rules),
        ];

        return $product;
    }

    private function generateRandomHTML(int $count, array $tags): string
    {
        $output = '';
        for ($i = 0; $i < $count; ++$i) {
            $tag = Random::getRandomArrayElement($tags);
            $text = $this->faker->words(random_int(1, 10), true);
            $output .= sprintf('<%1$s>%2$s</%1$s>', $tag, $text);
            $output .= '<br/>';
        }

        return $output;
    }

    private function createConfigurators(): array
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

    /**
     * @param array $groups
     *
     * @return array
     */
    private function buildProductConfigurator(array $groups): array
    {
        $optionIds = $this->getRandomOptions($groups);

        $options = array_map(
            function ($id) {
                $price = random_int(2, 10);

                return [
                    'optionId' => $id,
                    'price' => ['gross' => $price, 'net' => $price / 1.19, 'linked' => true],
                ];
            },
            $optionIds
        );

        return $options;
    }

    private function getRandomOptions(array $groups)
    {
        $optionIds = [];
        foreach ($groups as $group) {
            $ids = array_column($group['options'], 'id');

            $count = random_int(2, \count($ids));

            $x = (int) round(\count($ids) / 3);

            $offset = random_int(0, $x);

            $optionIds = array_merge(
                $optionIds,
                \array_slice($ids, $offset, $count)
            );
        }

        return $optionIds;
    }

    private function createServices(): array
    {
        $data = [
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'warranty',
                'options' => [
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '1 year'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '2 years'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => '3 years'],
                ],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'assembly',
                'options' => [
                    ['id' => Uuid::uuid4()->getHex(), 'name' => 'full assembly'],
                    ['id' => Uuid::uuid4()->getHex(), 'name' => 'half assembly'],
                ],
            ],
        ];

        $this->writer->insert(ConfigurationGroupDefinition::class, $data, $this->getContext());

        return $data;
    }

    private function buildProductServices(array $services, array $taxes)
    {
        $optionIds = $this->getRandomOptions($services);

        return array_map(function ($optionId) use ($taxes) {
            $price = random_int(5, 100);

            return [
                'price' => ['gross' => $price, 'net' => $price / 1.19, 'linked' => true],
                'taxId' => $taxes[random_int(0, \count($taxes) - 1)],
                'optionId' => $optionId,
            ];
        }, $optionIds);
    }

    private function getRandomImage(?string $text): string
    {
        $images = (new Finder())
            ->files()
            ->in($this->projectDir . '/build/media')
            ->name('/\.(jpg|png)$/')
            ->getIterator();

        $images = array_values(iterator_to_array($images));

        if (\count($images)) {
            return $images[random_int(0, \count($images) - 1)]->getPathname();
        }

        if (!$text) {
            $text = $this->faker->word;
        }

        return $this->tmpImages[] = $this->faker->imageGenerator(null, $this->faker->numberBetween(600, 800), $this->faker->numberBetween(400, 600), 'jpg', true, $text, '#d8dde6', '#333333');
    }

    private function cleanupImages(): void
    {
        foreach ($this->tmpImages as $image) {
            unlink($image);
        }
    }

    private function createOrders(int $limit): void
    {
        $products = $this->connection->fetchAll('
        SELECT LOWER(HEX(product.id)) AS id,
               product.price,
               trans.name
        FROM product
        LEFT JOIN product_translation trans ON product.id = trans.product_id
        LIMIT 500');
        $customerIds = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as id FROM customer LIMIT 200');
        $customerIds = array_column($customerIds, 'id');

        $this->io->section("Generating {$limit} orders...");
        $this->io->progressStart($limit);

        $lineItems = array_map(
            function ($product) {
                $id = $product['id'];

                if (!$product['price']) {
                    $price = random_int(20, 2000);
                } else {
                    $raw = json_decode((string) $product['price'], true);
                    $price = $raw['gross'];
                }

                $quantity = random_int(1, 10);

                $price = new QuantityPriceDefinition(
                    $price,
                    new TaxRuleCollection([
                        new TaxRule($this->faker->randomElement([19, 7])),
                    ]),
                    $quantity,
                    true
                );

                return (new LineItem($id, ProductCollector::LINE_ITEM_TYPE, $quantity))
                    ->setPayload(['id' => $id])
                    ->setPriceDefinition($price)
                    ->setLabel($product['name']);
            },
            $products
        );

        $payload = [];

        $contexts = [];
        $lineItems = new LineItemCollection($lineItems);

        for ($i = 1; $i <= $limit; ++$i) {
            $token = Uuid::uuid4()->getHex();

            $customerId = $this->faker->randomElement($customerIds);

            $options = [
                CheckoutContextService::CUSTOMER_ID => $customerId,
            ];

            if (isset($contexts[$customerId])) {
                $context = $contexts[$customerId];
            } else {
                $context = $this->contextFactory->create($token, Defaults::SALES_CHANNEL, $options);
                $contexts[$customerId] = $context;
            }

            $itemCount = random_int(3, 5);

            $offset = random_int(0, $lineItems->count()) - 10;

            $new = $lineItems->slice($offset, $itemCount);

            $cart = new Cart('shopware', $token);
            $cart->addLineItems($new);

            $cart = $this->processor->process($cart, $context);

            $payload[] = $this->orderConverter->convertToOrder($cart, $context);

            if (\count($payload) >= 20) {
                $this->writer->upsert(OrderDefinition::class, $payload, $this->getContext());
                $this->io->progressAdvance(\count($payload));
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->writer->upsert(OrderDefinition::class, $payload, $this->getContext());
        }

        $this->io->progressFinish();
    }

    private function createMedia(int $limit): void
    {
        $context = Context::createDefaultContext();
        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);

        $this->io->section("Generating {$limit} media items...");
        $this->io->progressStart($limit);

        for ($i = 0; $i < $limit; ++$i) {
            $file = $this->getRandomFile();

            $mediaId = \Ramsey\Uuid\Uuid::uuid4()->getHex();
            $this->writer->insert(
                MediaDefinition::class,
                [['id' => $mediaId, 'name' => "File #{$i}: {$file}"]],
                $this->getContext()
            );

            $this->mediaUpdater->persistFileToMedia(
                new MediaFile(
                    $file,
                    mime_content_type($file),
                    pathinfo($file, PATHINFO_EXTENSION),
                    filesize($file)
                ),
                $this->fileNameProvider->provide(
                    pathinfo($file, PATHINFO_FILENAME),
                    pathinfo($file, PATHINFO_EXTENSION),
                    $mediaId,
                    $context
                ),
                $mediaId,
                $context
            );

            $this->io->progressAdvance(1);
        }
        $this->io->progressFinish();
    }

    private function getRandomFile(): string
    {
        $files = array_keys(iterator_to_array(
            (new Finder())
            ->files()
            ->in(__DIR__ . '/../Resources/demo-media')
            ->getIterator()
        ));

        return $files[random_int(0, \count($files) - 1)];
    }

    private function getOrCreateDefaultFolder(Context $context): ?string
    {
        $mediaFolderId = null;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entity', 'product'));
        $criteria->setLimit(1);

        $defaultFolders = $this->defaultFolderRepository->search($criteria, $context);

        if ($defaultFolders->count() > 0) {
            /** @var MediaDefaultFolderEntity $defaultFolder */
            $defaultFolder = $defaultFolders->first();

            if ($defaultFolder->getFolderId()) {
                return $defaultFolder->getFolderId();
            }

            $mediaFolderId = Uuid::uuid4()->getHex();
            $this->defaultFolderRepository->upsert([
                [
                    'id' => $defaultFolder->getId(),
                    'folder' => [
                        'id' => $mediaFolderId,
                        'name' => 'Product Media',
                        'useParentConfiguration' => false,
                        'configuration' => [],
                    ],
                ],
            ], $context);
        }

        return $mediaFolderId;
    }

    private function createProperties(int $count)
    {
        if ($count <= 0) {
            return [];
        }

        $this->io->writeln("Generating {$count} property groups");

        $this->io->progressStart($count);

        $context = Context::createDefaultContext();

        $optionIds = [];

        for ($i = 0; $i <= $count; ++$i) {
            $options = [];

            $x = random_int(20, 100);

            for ($i2 = 0; $i2 <= $x; ++$i2) {
                $id = Uuid::uuid4()->getHex();
                $optionIds[] = $id;
                $options[] = ['id' => $id, 'name' => $this->faker->colorName];
            }

            $this->configurationGroupRepository->create(
                [
                    [
                        'id' => Uuid::uuid4()->getHex(),
                        'name' => $this->faker->word,
                        'options' => $options,
                        'description' => $this->faker->text,
                        'sorting_type' => 'numeric',
                        'display_type' => 'text',
                    ],
                ],
                $context
            );

            $this->io->progressAdvance(1);
        }
        $this->io->progressFinish();

        return $optionIds;
    }

    private function getProperties()
    {
        $options = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as id FROM configuration_group_option LIMIT 5000');
        if (!empty($options)) {
            return array_column($options, 'id');
        }

        return $this->createProperties(10);
    }
}
