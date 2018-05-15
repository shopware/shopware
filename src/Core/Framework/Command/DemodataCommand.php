<?php declare(strict_types=1);

namespace Shopware\Framework\Command;

use Bezhanov\Faker\Provider\Commerce;
use bheller\ImagesGenerator\ImagesGeneratorProvider;
use Faker\Factory;
use Faker\Generator;
use League\Flysystem\FilesystemInterface;
use Shopware\Content\Category\Repository\CategoryRepository;
use Shopware\System\Configuration\Definition\ConfigurationGroupDefinition;
use Shopware\Application\Context\Definition\ContextRuleDefinition;
use Shopware\Application\Context\Repository\ContextRuleRepository;
use Shopware\Checkout\Customer\Definition\CustomerDefinition;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Write\EntityWriterInterface;
use Shopware\Framework\ORM\Write\WriteContext;
use Shopware\Content\Media\Repository\MediaAlbumRepository;
use Shopware\Content\Product\ProductDefinition;
use Shopware\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Content\Product\ProductRepository;
use Shopware\Application\Context\Rule\CalculatedCart\GoodsPriceRule;
use Shopware\Application\Context\Rule\Container\AndRule;
use Shopware\Application\Context\Rule\Container\NotRule;
use Shopware\Application\Context\Rule\Context\CurrencyRule;
use Shopware\Application\Context\Rule\Context\CustomerGroupRule;
use Shopware\Application\Context\Rule\Context\DateRangeRule;
use Shopware\Application\Context\Rule\Context\IsNewCustomerRule;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Shopware\Content\Product\Util\VariantGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

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

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var ContextRuleRepository
     */
    private $contextRuleRepository;

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @var string
     */
    private $tenantId;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var MediaAlbumRepository
     */
    private $albumRepository;
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * Images to be deleted after generating data
     *
     * @var string[]
     */
    private $tmpImages = [];

    public function __construct(
        ?string $name = null,
        EntityWriterInterface $writer,
        VariantGenerator $variantGenerator,
        FilesystemInterface $filesystem,
        ContainerInterface $container
    ) {
        parent::__construct($name);
        $this->writer = $writer;
        $this->filesystem = $filesystem;

        $this->variantGenerator =  $variantGenerator;
        $this->productRepository = $container->get(ProductRepository::class);
        $this->contextRuleRepository = $container->get(ContextRuleRepository::class);
        $this->categoryRepository = $container->get(CategoryRepository::class);
        $this->albumRepository = $container->get(MediaAlbumRepository::class);
    }

    protected function configure()
    {
        $this->addOption('tenant-id', 't', InputOption::VALUE_REQUIRED, 'Tenant id');
        $this->addOption('products', 'p', InputOption::VALUE_REQUIRED, 'Product count', 500);
        $this->addOption('categories', 'c', InputOption::VALUE_REQUIRED, 'Category count', 10);
        $this->addOption('manufacturers', 'm', InputOption::VALUE_REQUIRED, 'Manufacturer count', 50);
        $this->addOption('customers', null, InputOption::VALUE_REQUIRED, 'Customer count', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $env = $this->getContainer()->getParameter('kernel.environment');

        if ($env !== 'prod') {
            $output->writeln('Demo data command should only be used in production environment. You can provide the environment as follow `framework:demodata -eprod`');

            return;
        }
        $tenantId = $input->getOption('tenant-id');

        if (!$tenantId) {
            throw new \Exception('No tenant id provided');
        }
        if (!Uuid::isValid($tenantId)) {
            throw new \Exception('Invalid uuid provided');
        }
        $this->tenantId = $tenantId;

        $this->io = new SymfonyStyle($input, $output);
        $this->faker = Factory::create('de_DE');
        $this->faker->addProvider(new Commerce($this->faker));
        $this->faker->addProvider(new ImagesGeneratorProvider($this->faker));

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

        $this->cleanupImages();

        $this->io->newLine();

        $this->io->success('Successfully created demodata.');
    }

    private function getContext()
    {
        return WriteContext::createFromApplicationContext(
            ApplicationContext::createDefaultContext($this->tenantId)
        );
    }

    private function createCategory($count = 10)
    {
        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $payload[] = [
                'id' => Uuid::uuid4()->getHex(),
                'catalogId' => Defaults::CATALOG,
                'name' => $this->randomDepartment(),
            ];
        }

        $parents = $payload;
        foreach ($parents as $category) {
            for ($x = 0; $x < 40; ++$x) {
                $payload[] = [
                    'id' => Uuid::uuid4()->getHex(),
                    'catalogId' => Defaults::CATALOG,
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
            $this->categoryRepository->upsert($chunk, ApplicationContext::createDefaultContext($this->tenantId));
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
                'applicationId' => Defaults::APPLICATION,
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
            'applicationId' => Defaults::APPLICATION,
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

        $albumId = Uuid::uuid4()->getHex();
        $this->io->section('Creating default media album.');
        $this->albumRepository->create([['id' => $albumId, 'name' => 'Products']], ApplicationContext::createDefaultContext($this->tenantId));

        $this->io->section(sprintf('Generating %d products...', $count));
        $this->io->progressStart($count);

        $configurator = $this->createConfigurators();

        $services = $this->createServices();

        for ($i = 0; $i < $count; ++$i) {
            $product = $this->createSimpleProduct($categories, $manufacturer, $contextRules);

            $imagePath = $this->getRandomImage($product['name']);
            $product['media'] = [
                [
                    'isCover' => true,
                    'media' => [
                        'fileName' => $product['id'] . '.' . pathinfo($imagePath, PATHINFO_EXTENSION),
                        'mimeType' => mime_content_type($imagePath),
                        'fileSize' => filesize($imagePath),
                        'albumId' => $albumId,
                        'name' => 'Product image of ' . $product['name'],
                    ]
                ]
            ];

            $mediaFile = fopen($imagePath, 'rb');
            $this->filesystem->writeStream($product['id'] . '.' . pathinfo($imagePath, PATHINFO_EXTENSION), $mediaFile);
            fclose($mediaFile);

            $hasServices = random_int(1, 100) <= 5;
            if ($hasServices) {
                $product['services'] = $this->buildProductServices($services);
            }

            $isConfigurator = random_int(1, 100) <= 5;
            if ($isConfigurator) {
                $product['configurators'] = $this->buildProductConfigurator($configurator);

                $product['datasheet'] = array_map(function ($config) {
                    return ['id' => $config['optionId']];
                }, $product['configurators']);
            }

            if ($isConfigurator) {
                $this->io->progressAdvance();

                $this->productRepository->upsert([$product], ApplicationContext::createDefaultContext($this->tenantId));

                $variantEvent = $this->variantGenerator->generate($product['id'], ApplicationContext::createDefaultContext($this->tenantId));
                $productEvents = $variantEvent->getEventByDefinition(ProductDefinition::class);
                $variantProductIds = $productEvents->getIds();

                $variantImagePayload = [];
                foreach ($variantProductIds as $y => $variantProductId) {
                    $imagePath = $this->getRandomImage($product['name'] . ' #' . $y);

                    $variantImagePayload[] = [
                        'id' => $variantProductId,
                        'media' => [
                            [
                                'isCover' => true,
                                'media' => [
                                    'fileName' => $variantProductId . '.' . pathinfo($imagePath, PATHINFO_EXTENSION),
                                    'mimeType' => mime_content_type($imagePath),
                                    'fileSize' => filesize($imagePath),
                                    'albumId' => $albumId,
                                    'name' => 'Product image of ' . $product['name'],
                                ]
                            ]
                        ]
                    ];

                    $mediaFile = fopen($imagePath, 'rb');
                    $this->filesystem->writeStream($variantProductId . '.' . pathinfo($imagePath, PATHINFO_EXTENSION), $mediaFile);
                    fclose($mediaFile);
                }

                $this->productRepository->update($variantImagePayload, ApplicationContext::createDefaultContext($this->tenantId));

                continue;
            }

            $payload[] = $product;

            if (count($payload) >= 20) {
                $this->io->progressAdvance(count($payload));
                $this->productRepository->upsert($payload, ApplicationContext::createDefaultContext($this->tenantId));
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->productRepository->upsert($payload, ApplicationContext::createDefaultContext($this->tenantId));
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
        $ids = $this->contextRuleRepository->searchIds(new Criteria(), ApplicationContext::createDefaultContext($this->tenantId));

        if (!empty($ids->getIds())) {
            return $ids->getIds();
        }

        $pool = [
            new IsNewCustomerRule(),
            new DateRangeRule(new \DateTime(), (new \DateTime())->modify('+2 day')),
            new GoodsPriceRule(5000, GoodsPriceRule::OPERATOR_GTE),
            new NotRule([new CustomerGroupRule([Defaults::FALLBACK_CUSTOMER_GROUP])]),
            new NotRule([new CurrencyRule([Defaults::CURRENCY])]),
        ];

        $payload = [];
        for ($i = 0; $i < 20; ++$i) {
            $rules = \array_slice($pool, random_int(0, count($pool) - 2), random_int(1, 2));

            $payload[] = [
                'id' => Uuid::uuid4()->getHex(),
                'priority' => $i,
                'name' => 'Random rule value',
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
                'quantityStart' => 11,
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
                    'price' => ['gross' => $price, 'net' => $price / 1.19],
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
                array_slice($ids, $offset, $count)
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

    private function buildProductServices(array $services)
    {
        $optionIds = $this->getRandomOptions($services);

        return array_map(function ($optionId) {
            $price = random_int(5, 100);

            return [
                'price' => ['gross' => $price, 'net' => $price / 1.19],
                'taxId' => '4926035368e34d9fa695e017d7a231b9',
                'optionId' => $optionId,
            ];
        }, $optionIds);
    }

    private function getRandomImage(?string $text): string
    {
        $images = (new Finder())
            ->files()
            ->in($this->getContainer()->getParameter('kernel.project_dir') . '/build/media')
            ->name('/\.(jpg|png)$/')
            ->getIterator();

        $images = array_values(iterator_to_array($images));

        if (count($images)) {
            return $images[random_int(0, \count($images) - 1)]->getPathname();
        }

        if (!$text) {
            $text = $this->faker->word;
        }

        return $this->tmpImages[] = $this->faker->imageGenerator(null, $this->faker->numberBetween(600, 800), $this->faker->numberBetween(400, 600), 'jpg', true, $text, '#d8dde6', '#333333');
    }

    private function cleanupImages()
    {
        foreach ($this->tmpImages as $image) {
            unlink($image);
        }
    }
}
