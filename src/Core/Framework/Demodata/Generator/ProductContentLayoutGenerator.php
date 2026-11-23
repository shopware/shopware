<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\ContentSystemLayoutProvisioner;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class ProductContentLayoutGenerator implements DemodataGeneratorInterface
{
    public function __construct(
        private readonly ContentSystemLayoutProvisioner $layoutProvisioner,
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

        foreach ($productIds as $productId) {
            $this->layoutProvisioner->provisionFromPreset(
                $faker->format('sentence', [3]),
                'Sw:ProductDetailPage',
                ProductContentLayoutDefinition::CONTENT_LAYOUT_ENTITY_TYPE,
                $context->getContext(),
                entityId: $productId,
            );

            $console->progressAdvance();
        }

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
}
