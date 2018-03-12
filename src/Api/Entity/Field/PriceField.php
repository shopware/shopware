<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Entity\Write\DataStack\KeyValuePair;
use Shopware\Api\Entity\Write\EntityExistence;
use Shopware\Api\Entity\Write\FieldAware\SqlParseAware;
use Shopware\Context\Struct\ShopContext;

class PriceField extends JsonObjectField implements SqlParseAware
{
    public function __invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if ($existence->exists()) {
            $this->validate($this->getUpdateConstraints(), $key, $value);
        } else {
            $this->validate($this->getInsertConstraints(), $key, $value);
        }

        if (is_array($value)) {
            $value = json_encode($value);
        }

        yield $this->storageName => $value;
    }

    public function parse(string $root, ShopContext $context): string
    {
        return sprintf('(CAST(JSON_UNQUOTE(JSON_EXTRACT(`%s`.`%s`, "$.gross")) AS DECIMAL))', $root, $this->storageName);
    }
}
