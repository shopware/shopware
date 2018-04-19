<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Product;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\Uuid;

class VariationJsonUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function update(array $productIds, ApplicationContext $context)
    {
        $sql = <<<SQL
UPDATE product, product_variation SET product.variation_ids = (
    SELECT CONCAT('[', GROUP_CONCAT(JSON_QUOTE(LOWER(HEX(product_variation.configuration_group_option_id)))), ']')
    FROM product_variation
    WHERE product_variation.product_id = product.id
    AND product_variation.product_tenant_id = :tenant
)
WHERE product_variation.product_id = product.id
AND product.tenant_id = :tenant
SQL;

        $tenantId = Uuid::fromHexToBytes($context->getTenantId());
        if (empty($productIds)) {
            $this->connection->executeUpdate($sql, ['tenant' => $tenantId]);

            return;
        }

        $sql .= ' AND product.id IN (:ids)';

        $bytes = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $productIds);

        $this->connection->executeUpdate(
            $sql,
            ['ids' => $bytes, 'tenant' => $tenantId],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }
}
