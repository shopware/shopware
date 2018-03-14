<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Write\FieldAware;

use Shopware\Api\Entity\Dbal\QueryBuilder;
use Shopware\Context\Struct\ShopContext;

/**
 * Allows to parse the field access by your own. Helpful if the field contains json or other nested data
 * which related to the provided ShopContext.
 */
interface DbalJoinAware
{
    public function join(QueryBuilder $query, string $root, ShopContext $context): void;
}
