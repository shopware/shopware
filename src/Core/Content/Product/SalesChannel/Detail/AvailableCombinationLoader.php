<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('inventory')]
class AvailableCombinationLoader extends AbstractAvailableCombinationLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getDecorated(): AbstractAvailableCombinationLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $productId, Context $context, string $salesChannelId): AvailableCombinationResult
    {
        $query = $this->connection->createQueryBuilder();
        $query->from('product');
        $query->leftJoin('product', 'product', 'parent', 'product.parent_id = parent.id');

        $query->andWhere('product.parent_id = :id');
        $query->andWhere('product.version_id = :versionId');
        $query->andWhere('IFNULL(product.active, parent.active) = :active');
        $query->andWhere('product.option_ids IS NOT NULL');

        $query->setParameter('id', Uuid::fromHexToBytes($productId));
        $query->setParameter('versionId', Uuid::fromHexToBytes($context->getVersionId()));
        $query->setParameter('active', true);

        $query->innerJoin('product', 'product_visibility', 'visibilities', 'product.visibilities = visibilities.product_id');
        $query->andWhere('visibilities.sales_channel_id = :salesChannelId');
        $query->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId));

        $query->select([
            'LOWER(HEX(product.id))',
            'product.option_ids as options',
            'product.product_number as productNumber',
            'product.available',
        ]);

        $combinations = $query->executeQuery()->fetchAllAssociative();
        $combinations = FetchModeHelper::groupUnique($combinations);

        $result = new AvailableCombinationResult();

        foreach ($combinations as $combination) {
            try {
                $options = json_decode((string) $combination['options'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            $available = (bool) $combination['available'];
            $result->addCombination($options, $available);
        }

        return $result;
    }
}
