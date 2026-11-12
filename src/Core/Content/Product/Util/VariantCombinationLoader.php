<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Util;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Product\Util\VariantCombinationLoaderTest
 */
#[Package('inventory')]
class VariantCombinationLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, array{options: list<string>, productNumber: string, type: string, productStates?: string|null}>
     */
    public function load(string $productId, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(product.id)) AS id',
            'LOWER(HEX(product_option.property_group_option_id)) AS optionId',
            'product.product_number AS productNumber',
            'product.type AS type',
        );

        if (!Feature::isActive('v6.8.0.0')) {
            $query->addSelect('product.states AS productStates');
        }

        $query->from('product');
        $query->innerJoin(
            'product',
            'product_option',
            'product_option',
            'product_option.product_id = product.id AND product_option.product_version_id = product.version_id'
        );
        $query->where('product.parent_id = :id');
        $query->andWhere('product.version_id = :versionId');
        $query->setParameter('id', Uuid::fromHexToBytes($productId));
        $query->setParameter('versionId', Uuid::fromHexToBytes($context->getVersionId()));

        $combinations = [];

        /** @var array{id: string, optionId: string, productNumber: string, type: string, productStates?: string|null} $row */
        foreach ($query->executeQuery()->fetchAllAssociative() as $row) {
            $id = $row['id'];

            if (!isset($combinations[$id])) {
                $combinations[$id] = ['options' => [], 'productNumber' => $row['productNumber'], 'type' => $row['type']];

                if (\array_key_exists('productStates', $row)) {
                    $combinations[$id]['productStates'] = $row['productStates'];
                }
            }

            $combinations[$id]['options'][] = $row['optionId'];
        }

        return $combinations;
    }
}
