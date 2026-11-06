<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class ProductContentLayoutGenerator implements DemodataGeneratorInterface
{
    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     * @param EntityRepository<ProductContentLayoutCollection> $productContentLayoutRepository
     */
    public function __construct(
        private readonly EntityRepository $contentLayoutRepository,
        private readonly EntityRepository $productContentLayoutRepository,
        private readonly Connection $connection,
    ) {
    }

    public function getDefinition(): string
    {
        return ProductContentLayoutDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $numberOfItems = max($numberOfItems, 1);

        $productIds = $this->getProductIds($numberOfItems);

        if ($productIds === []) {
            return;
        }

        $faker = $context->getFaker();

        $console = $context->getConsole();
        $console->progressStart(\count($productIds));

        $layouts = [];
        $assignments = [];

        foreach ($productIds as $productId) {
            $layoutId = Uuid::randomHex();

            $layouts[] = [
                'id' => $layoutId,
                'name' => $faker->words(3, true),
                'version' => '1.0.0',
                'rootSource' => 'product',
                'layout' => $this->buildLayout($faker),
            ];

            $assignments[] = [
                'id' => Uuid::randomHex(),
                'productId' => $productId,
                'salesChannelId' => null,
                'contentLayoutId' => $layoutId,
            ];

            $console->progressAdvance();
        }

        $this->contentLayoutRepository->create($layouts, $context->getContext());
        $this->productContentLayoutRepository->create($assignments, $context->getContext());

        $console->progressFinish();
    }

    /**
     * The most recently created main products, so a demo run assigns the layout to freshly
     * generated demo products rather than to a shop's existing (possibly real) products.
     *
     * @return list<string>
     */
    private function getProductIds(int $limit): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(`id`))
             FROM `product`
             WHERE `parent_id` IS NULL AND `version_id` = UNHEX(:live)
             ORDER BY `auto_increment` DESC
             LIMIT ' . $limit,
            ['live' => Defaults::LIVE_VERSION]
        );

        return $ids;
    }

    /**
     * A minimal product detail page: a grid container with a single text element. Missing element
     * properties are filled from the element-type defaults by the layout default seeder on write.
     *
     * @return list<array<string, mixed>>
     */
    private function buildLayout(Generator $faker): array
    {
        return [
            [
                'id' => Uuid::randomHex(),
                'component' => 'Sw:Grid:Container',
                'properties' => [
                    'mode' => 'auto-fit',
                    'itemMinWidth' => '400px',
                ],
                'slots' => [
                    'content' => [
                        [
                            'id' => Uuid::randomHex(),
                            'component' => 'Sw:Content:Text',
                            'properties' => [
                                'text' => '<p>' . $faker->paragraph(20) . '</p>',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
