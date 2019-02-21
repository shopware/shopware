<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use function Flag\next1207;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Configuration\ConfigurationGroupDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Util\VariantGenerator;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Util\Random;
use Symfony\Component\Finder\Finder;

class ProductGenerator implements DemodataGeneratorInterface
{
    public const OPTIONS_WITH_MEDIA = 'with_media';
    public const OPTIONS_WITH_CONFIGURATOR = 'with_configurator';
    public const OPTIONS_WITH_SERVICES = 'with_services';

    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var EntityRepositoryInterface
     */
    private $defaultFolderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var FileSaver
     */
    private $fileSaver;

    /**
     * @var FileNameProvider
     */
    private $fileNameProvider;

    /**
     * @var array
     */
    private $tmpImages = [];

    /**
     * @var string[]
     */
    private $productImages = [];

    /**
     * @var VariantGenerator
     */
    private $variantGenerator;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        EntityWriterInterface $writer,
        EntityRepositoryInterface $defaultFolderRepository,
        EntityRepositoryInterface $taxRepository,
        EntityRepositoryInterface $productRepository,
        FileSaver $fileSaver,
        FileNameProvider $fileNameProvider,
        VariantGenerator $variantGenerator,
        Connection $connection
    ) {
        $this->writer = $writer;
        $this->defaultFolderRepository = $defaultFolderRepository;
        $this->taxRepository = $taxRepository;
        $this->productRepository = $productRepository;
        $this->fileSaver = $fileSaver;
        $this->fileNameProvider = $fileNameProvider;
        $this->variantGenerator = $variantGenerator;
        $this->connection = $connection;
    }

    public function getDefinition(): string
    {
        return ProductDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->createProduct(
            $context,
            $numberOfItems,
            isset($options[self::OPTIONS_WITH_MEDIA]),
            isset($options[self::OPTIONS_WITH_CONFIGURATOR]),
            isset($options[self::OPTIONS_WITH_SERVICES])
        );

        $context->getConsole()->comment('Deleting temporary images...');
        $this->cleanupImages();
    }

    private function createProduct(
        DemodataContext $context,
        $count = 500,
        bool $withMedia = false,
        bool $withConfigurator = false,
        bool $withServices = false
    ): void {
        $configurator = [];
        if ($withConfigurator) {
            $configurator = $this->createConfigurators($context);
        }

        $services = [];
        if ($withServices) {
            $services = $this->createServices($context);
        }

        $mediaFolderId = null;
        if (next1207()) {
            $mediaFolderId = $this->getOrCreateDefaultFolder($context);
        }

        $taxes = $this->getTaxes($context->getContext());
        $properties = $this->getProperties();

        $context->getConsole()->progressStart($count);

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $product = $this->createSimpleProduct($context, $taxes);

            if ($withMedia) {
                $imagePath = $this->getRandomImage($context, $product['name']);
                $mediaId = Uuid::uuid4()->getHex();
                $product['cover'] = [
                    'media' => [
                        'id' => $mediaId,
                        'name' => 'Product image of ' . $product['name'],
                        'mediaFolderId' => $mediaFolderId,
                    ],
                ];

                $this->productImages[$mediaId] = $imagePath;
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
                $context->getConsole()->progressAdvance();

                $this->productRepository->upsert([$product], $context->getContext());

                $variantEvent = $this->variantGenerator->generate($product['id'], $context->getContext());
                $variantProductIds = $variantEvent->getEventByDefinition(ProductDefinition::class)->getIds();

                $variantImagePayload = [];
                foreach ($variantProductIds as $y => $variantProductId) {
                    $imagePath = $this->getRandomImage($context, $product['name'] . ' #' . $y);

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

                    $this->productImages[$mediaId] = $imagePath;
                }

                $this->productRepository->update($variantImagePayload, $context->getContext());

                continue;
            }

            $payload[] = $product;

            if (\count($payload) >= 50) {
                $context->getConsole()->progressAdvance(\count($payload));
                $this->write($payload, $context);
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->write($payload, $context);
        }

        $context->getConsole()->progressFinish();
    }

    private function write(array $payload, DemodataContext $context): void
    {
        $writeContext = WriteContext::createFromContext($context->getContext());

        $this->writer->upsert(ProductDefinition::class, $payload, $writeContext);
        $this->importImages($context);

        $context->add(ProductDefinition::class, ...array_column($payload, 'id'));
    }

    private function importImages(DemodataContext $context): void
    {
        foreach ($this->productImages as $id => $file) {
            $this->fileSaver->persistFileToMedia(
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
                    $context->getContext()
                ),
                $id,
                $context->getContext()
            );
        }
    }

    private function createServices(DemodataContext $context): array
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

        $writeContext = WriteContext::createFromContext($context->getContext());
        $this->writer->insert(ConfigurationGroupDefinition::class, $data, $writeContext);

        $context->add(ConfigurationGroupDefinition::class, ...array_column($data, 'id'));

        return $data;
    }

    private function getOrCreateDefaultFolder(DemodataContext $context): ?string
    {
        $mediaFolderId = null;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entity', 'product'));
        $criteria->addAssociation('folder', new Criteria());
        $criteria->setLimit(1);

        $defaultFolders = $this->defaultFolderRepository->search($criteria, $context->getContext());

        if ($defaultFolders->count() > 0) {
            /** @var MediaDefaultFolderEntity $defaultFolder */
            $defaultFolder = $defaultFolders->first();

            if ($defaultFolder->getFolder()) {
                return $defaultFolder->getFolder()->getId();
            }

            $mediaFolderId = Uuid::uuid4()->getHex();
            $this->defaultFolderRepository->upsert([
                [
                    'id' => $defaultFolder->getId(),
                    'folder' => [
                        'id' => $mediaFolderId,
                        'defaultFolderId' => $defaultFolder->getId(),
                        'name' => 'Product Media',
                        'useParentConfiguration' => false,
                        'configuration' => [],
                    ],
                ],
            ], $context->getContext());

            $context->add(MediaDefaultFolderEntity::class, $mediaFolderId);
        }

        return $mediaFolderId;
    }

    private function getTaxes(Context $context)
    {
        return array_values($this->taxRepository->search(new Criteria(), $context)->getIds());
    }

    private function createSimpleProduct(DemodataContext $context, array $taxes): array
    {
        $price = random_int(1, 1000);
        $manufacturer = $context->getIds(ProductManufacturerDefinition::class);
        $categories = $context->getIds(CategoryDefinition::class);
        $rules = $context->getIds(RuleDefinition::class);

        $faker = $context->getFaker();
        $product = [
            'id' => Uuid::uuid4()->getHex(),
            'price' => ['gross' => $price, 'net' => $price / 1.19, 'linked' => true],
            'name' => $faker->productName,
            'description' => $faker->text(),
            'descriptionLong' => $this->generateRandomHTML(
                10,
                ['b', 'i', 'u', 'p', 'h1', 'h2', 'h3', 'h4', 'cite'],
                $context
            ),
            'taxId' => $taxes[array_rand($taxes)],
            'manufacturerId' => $manufacturer[array_rand($manufacturer)],
            'active' => true,
            'categories' => [
                ['id' => $categories[array_rand($categories)]],
            ],
            'stock' => $faker->randomNumber(),
            'priceRules' => $this->createPrices($rules),
        ];

        return $product;
    }

    private function generateRandomHTML(int $count, array $tags, DemodataContext $context): string
    {
        $output = '';
        for ($i = 0; $i < $count; ++$i) {
            $tag = Random::getRandomArrayElement($tags);
            $text = $context->getFaker()->words(random_int(1, 10), true);
            $output .= sprintf('<%1$s>%2$s</%1$s>', $tag, $text);
            $output .= '<br/>';
        }

        return $output;
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

    private function getRandomImage(DemodataContext $context, ?string $text): string
    {
        $images = array_values(
            iterator_to_array(
                (new Finder())
                    ->files()
                    ->in($context->getProjectDir() . '/build/media')
                    ->name('/\.(jpg|png)$/')
                    ->getIterator()
            )
        );

        if (\count($images)) {
            return $images[array_rand($images)]->getPathname();
        }

        if (!$text) {
            /** @var string $text */
            $text = $context->getFaker()->words(1, true);
        }

        return $this->tmpImages[] = $context->getFaker()->imageGenerator(
            null,
            $context->getFaker()->numberBetween(600, 800),
            $context->getFaker()->numberBetween(400, 600),
            'jpg',
            true,
            $text,
            '#d8dde6',
            '#333333'
        );
    }

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

    private function buildProductServices(array $services, array $taxes)
    {
        $optionIds = $this->getRandomOptions($services);

        return array_map(function ($optionId) use ($taxes) {
            $price = random_int(5, 100);

            return [
                'price' => ['gross' => $price, 'net' => $price / 1.19, 'linked' => true],
                'taxId' => $taxes[array_rand($taxes)],
                'optionId' => $optionId,
            ];
        }, $optionIds);
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

    private function cleanupImages(): void
    {
        foreach ($this->tmpImages as $image) {
            unlink($image);
        }
    }

    private function createConfigurators(DemodataContext $context): array
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

        $this->writer->insert(ConfigurationGroupDefinition::class, $data, WriteContext::createFromContext($context->getContext()));

        $context->add(ConfigurationGroupDefinition::class, ...array_column($data, 'id'));

        return $data;
    }

    private function getProperties()
    {
        $options = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as id FROM configuration_group_option LIMIT 5000');

        return array_column($options, 'id');
    }
}
