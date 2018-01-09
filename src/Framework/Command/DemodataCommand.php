<?php declare(strict_types=1);

namespace Shopware\Framework\Command;

use Faker\Factory;
use Faker\Generator;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Customer\Repository\CustomerRepository;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Repository\ProductManufacturerRepository;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Storefront\Context\StorefrontContextService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
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
     * @var TranslationContext
     */
    private $context;

    /**
     * @var SymfonyStyle
     */
    private $io;

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

        $this->io->title('Demodata Generator');

        $this->createCustomer($input->getOption('customers'));
        $categories = $this->createCategory($input->getOption('categories'));
        $manufacturer = $this->createManufacturer($input->getOption('manufacturers'));
        $this->createProduct($categories, $manufacturer, $input->getOption('products'));

        $this->io->newLine();

        $this->io->success('Successfully created demodata.');

        $arguments = ['command' => 'category:build:path'];
        $command = $this->getApplication()->find('category:build:path');
        $command->run(new ArrayInput($arguments), $output);

        $arguments = ['command' => 'dbal:refresh:index'];
        $command = $this->getApplication()->find('dbal:refresh:index');
        $command->run(new ArrayInput($arguments), $output);
    }

    private function getContext()
    {
        return $this->context ?? $this->context = new TranslationContext('FFA32A50-E2D0-4CF3-8389-A53F8D6CD594', true, null);
    }

    private function createCategory($count = 10)
    {
        $this->io->section("Generating {$count} categories...");
        $this->io->progressStart($count);

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $payload[] = [
                'id' => $this->faker->uuid,
                'name' => $this->faker->words(rand(1, 3), true),
                'parentId' => 'a1abd0ee-0aa6-4fcd-aef7-25b8b84e5943',
            ];
            $this->io->progressAdvance();
        }

        $this->io->progressFinish();

        $this->io->comment('Writing to database...');

        $repository = $this->getContainer()->get(CategoryRepository::class);
        $repository->upsert($payload, $this->getContext());

        $this->io->note(sprintf('Generating %d sub categories...', $count * 10));
        $this->io->progressStart($count);

        $childPayload = [];
        foreach ($payload as $category) {
            for ($x = 0; $x < 40; ++$x) {
                $childPayload[] = [
                    'id' => $this->faker->uuid,
                    'name' => $this->faker->words(rand(1, 3), true),
                    'parentId' => $category['id'],
                ];
                $this->io->progressAdvance();
            }
        }

        $this->io->progressFinish();
        $this->io->comment('Writing to database...');

        $repository->upsert($childPayload, $this->getContext());

        return array_merge(array_column($payload, 'id'), array_column($childPayload, 'id'));
    }

    private function createCustomer($count = 500)
    {
        $this->io->section(sprintf('Generating %d customers...', $count));
        $this->io->progressStart($count);

        $number = $this->faker->randomNumber;
        $password = password_hash('shopware', PASSWORD_BCRYPT, ['cost' => 13]);

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $id = $this->faker->uuid;
            $addressId = $this->faker->uuid;
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
                'defaultPaymentMethodId' => 'e84976ace9ab4928a3dcc387b66dbaa6',
                'groupId' => StorefrontContextService::FALLBACK_CUSTOMER_GROUP,
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

        $chunkSize = 150;
        if (count($payload) < $chunkSize) {
            $chunkSize = count($payload);
        }

        $chunks = array_chunk($payload, $chunkSize);
        $repository = $this->getContainer()->get(CustomerRepository::class);
        foreach ($chunks as $chunk) {
            $repository->upsert($chunk, $this->getContext());
            $this->io->progressAdvance($chunkSize);
        }

        $this->io->progressFinish();
        $this->io->comment('Writing to database...');
    }

    private function createProduct(array $categories, array $manufacturer, $count = 500)
    {
        $x = 0;
        $categoryCount = count($categories) - 1;
        $manufacturerCount = count($manufacturer) - 1;
        $writer = $this->getContainer()->get('shopware.api.entity_writer');

        $chunkSize = 150;
        if ($count < $chunkSize) {
            $chunkSize = $count;
        }

        $this->io->section(sprintf('Generating %d products...', $count));
        $progressbar = $this->io->createProgressBar($count);

        $payload = [];

        for ($i = 0; $i < $count; $i++) {
            $graduaded = [
                [
                    'customerGroupId' => '3294e6f6-372b-415f-ac73-71cbc191548f',
                    'price' => $this->faker->randomFloat(2, 60, 100),
                    'quantityStart' => 1,
                    'quantityEnd' => 4,
                ], [
                    'customerGroupId' => '3294e6f6-372b-415f-ac73-71cbc191548f',
                    'price' => $this->faker->randomFloat(2, 40, 59),
                    'quantityStart' => 5,
                ],
            ];

            $prices = [
                [
                    'customerGroupId' => '3294e6f6-372b-415f-ac73-71cbc191548f',
                    'price' => $this->faker->randomFloat(2, 60, 100),
                    'quantityStart' => 1,
                    'quantityEnd' => 4,
                ],
            ];

            $payload[] = [
                'id' => $this->faker->uuid,
                'name' => $this->faker->name,
                'description' => $this->faker->text(),
                'descriptionLong' => $this->faker->randomHtml(2, 3),
                'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9',
                'manufacturerId' => $manufacturer[random_int(0, $manufacturerCount)],
                'active' => true,
                'categories' => [
                    ['categoryId' => $categories[random_int(0, $categoryCount)]],
                ],
                'stock' => $this->faker->randomNumber(),
                'prices' => random_int(0, 1) === 1 ? $graduaded : $prices,
            ];

            if ($i % $chunkSize === 0) {
                $writer->upsert(ProductDefinition::class, $payload, WriteContext::createFromTranslationContext($this->getContext()));
                $progressbar->advance($chunkSize);
                $payload = [];
            }
        }

        $progressbar->finish();
    }

    private function createManufacturer($count = 50)
    {
        $this->io->section("Generating {$count} manufacturer...");
        $this->io->progressStart($count);

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $payload[] = [
                'id' => $this->faker->uuid,
                'name' => $this->faker->company,
                'link' => $this->faker->url,
            ];
            $this->io->progressAdvance();
        }

        $this->io->progressFinish();

        $this->io->comment('Writing to database...');

        $repository = $this->getContainer()->get(ProductManufacturerRepository::class);
        $repository->upsert($payload, $this->getContext());

        return array_column($payload, 'id');
    }
}
