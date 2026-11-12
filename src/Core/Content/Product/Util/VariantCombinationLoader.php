<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Util;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
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
     * @return array<string, array{options: list<string>, productNumber: string, type: string, productStates?: string}>
     */
    public function load(string $productId, Context $context): array
    {
        $optionsSelect = <<<'SQL'
            COALESCE(product.option_ids, (
                SELECT CONCAT('[', GROUP_CONCAT(JSON_QUOTE(LOWER(HEX(product_option.property_group_option_id)))), ']')
                FROM product_option
                WHERE product_option.product_id = product.id AND product_option.product_version_id = product.version_id
            )) as options
        SQL;

        $query = $this->connection->createQueryBuilder();
        $query->select('LOWER(HEX(product.id))', $optionsSelect, 'product.product_number as productNumber', 'product.type as type');

        if (!Feature::isActive('v6.8.0.0')) {
            $query->addSelect('product.states as productStates');
        }

        $query->from('product');
        $query->where('product.parent_id = :id');
        $query->andWhere('product.version_id = :versionId');
        $query->setParameter('id', Uuid::fromHexToBytes($productId));
        $query->setParameter('versionId', Uuid::fromHexToBytes($context->getVersionId()));

        $combinations = $query->executeQuery()->fetchAllAssociative();
        $combinations = FetchModeHelper::groupUnique($combinations);
        $combinations = array_filter($combinations, static fn (array $combination) => $combination['options'] !== null);
        /** @var array<string, array{options: string, productNumber: string, type: string, productStates?: string}> $combinations */
        foreach ($combinations as &$combination) {
            /** @var list<string> $options */
            $options = json_decode((string) $combination['options'], true, 512, \JSON_THROW_ON_ERROR);
            $combination['options'] = $options;
        }

        return $combinations;
    }
}
