<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

class ProductGenerator implements DemodataGeneratorInterface
{
    public const OPTIONS_WITH_MEDIA = 'with_media';

    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $numberRangeValueGenerator;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    public function __construct(
        EntityWriterInterface $writer,
        EntityRepositoryInterface $taxRepository,
        Connection $connection,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        EntityRepositoryInterface $mediaRepository,
        ProductDefinition $productDefinition
    ) {
        $this->writer = $writer;
        $this->taxRepository = $taxRepository;
        $this->connection = $connection;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->mediaRepository = $mediaRepository;
        $this->productDefinition = $productDefinition;
    }

    public function getDefinition(): string
    {
        return ProductDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->createProduct($context, $numberOfItems);
    }

    private function createProduct(DemodataContext $context, $count = 500): void
    {
        $visibilities = $this->buildVisibilities();

        $taxes = $this->getTaxes($context->getContext());
        $properties = $this->getProperties();

        $context->getConsole()->progressStart($count);

        $mediaIds = $this->getMediaIds($context->getContext());

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $product = $this->createSimpleProduct($context, $taxes);
            $product['visibilities'] = $visibilities;

            if ($mediaIds) {
                $product['cover'] = [
                    'mediaId' => Random::getRandomArrayElement($mediaIds),
                ];
            }

            $productProperties = \array_slice(
                $properties,
                random_int(0, max(0, count($properties) - 20)),
                random_int(10, 30)
            );

            $product['properties'] = array_map(function ($config) {
                return ['id' => $config];
            }, $productProperties);

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

        $this->writer->upsert($this->productDefinition, $payload, $writeContext);

        $context->add(ProductDefinition::class, ...array_column($payload, 'id'));
    }

    private function getTaxes(Context $context)
    {
        $this->taxRepository->create([
            ['name' => 'High tax', 'taxRate' => 19],
        ], $context);

        return $this->taxRepository->search(new Criteria(), $context);
    }

    private function getMediaIds(Context $context)
    {
        return array_values($this->mediaRepository->search(new PaginationCriteria(200), $context)->getIds());
    }

    private function createSimpleProduct(DemodataContext $context, EntitySearchResult $taxes): array
    {
        $price = random_int(1, 1000);
        $manufacturer = $context->getIds(ProductManufacturerDefinition::class);
        $categories = $context->getIds(CategoryDefinition::class);
        $rules = $context->getIds(RuleDefinition::class);
        $tax = $taxes->get(array_rand($taxes->getIds()));
        $reverseTaxrate = 1 + ($tax->getTaxRate() / 100);

        $faker = $context->getFaker();
        $product = [
            'id' => Uuid::randomHex(),
            'productNumber' => $this->numberRangeValueGenerator->getValue('product', $context->getContext(), null),
            'price' => ['gross' => $price, 'net' => $price / $reverseTaxrate, 'linked' => true],
            'name' => $faker->productName,
            'description' => $faker->text(),
            'descriptionLong' => $this->generateRandomHTML(
                10,
                ['b', 'i', 'u', 'p', 'h1', 'h2', 'h3', 'h4', 'cite'],
                $context
            ),
            'taxId' => $tax->getId(),
            'manufacturerId' => $manufacturer[array_rand($manufacturer)],
            'active' => true,
            'categories' => [
                ['id' => $categories[array_rand($categories)]],
            ],
            'stock' => $faker->randomNumber(),
            'prices' => $this->createPrices($rules, $reverseTaxrate),
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

    private function createPrices(array $rules, float $reverseTaxRate): array
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
                'price' => ['gross' => $gross, 'net' => $gross / $reverseTaxRate, 'linked' => true],
            ];

            $gross = random_int(1, 499);

            $prices[] = [
                'currencyId' => Defaults::CURRENCY,
                'ruleId' => $ruleId,
                'quantityStart' => 11,
                'price' => ['gross' => $gross, 'net' => $gross / $reverseTaxRate, 'linked' => true],
            ];
        }

        return $prices;
    }

    private function getProperties()
    {
        $options = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as id FROM property_group_option LIMIT 5000');

        return array_column($options, 'id');
    }

    private function buildVisibilities()
    {
        $ids = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as id FROM sales_channel LIMIT 100');

        return array_map(function ($id) {
            return ['salesChannelId' => $id['id'], 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL];
        }, $ids);
    }
}
