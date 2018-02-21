<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Entity\Write\FieldAware\SqlParseAware;
use Shopware\Context\Struct\ShopContext;

class PriceRulesField extends StringField implements SqlParseAware
{
    public function parse(string $root, ShopContext $context): string
    {
        //todo@dr Build keys by current context
        $keys = ['H_D_E', 'H_D', 'H_E'];

        $select = [];
        foreach ($keys as $key) {
            $select[] = sprintf('`%s`.`%s`->"$.%s"', $root, $this->getStorageName(), $key);
        }

        //fallback field
        $select[] = sprintf('`%s`.`%s`', $root, 'price');

        return sprintf('CAST(COALESCE(%s) AS DECIMAL)', implode(',', $select));
    }
}
