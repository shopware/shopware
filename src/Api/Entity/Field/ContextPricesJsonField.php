<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Entity\Write\DataStack\KeyValuePair;
use Shopware\Api\Entity\Write\EntityExistence;
use Shopware\Api\Entity\Write\FieldAware\SqlParseAware;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;

class ContextPricesJsonField extends JsonObjectField implements SqlParseAware
{
    public function __invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $value = $data->getValue();
        if (!empty($data->getValue())) {
            $value = $this->convertToStorage($value);
        }

        if ($existence->exists()) {
            $this->validate($this->getUpdateConstraints(), $data->getKey(), $value);
        } else {
            $this->validate($this->getInsertConstraints(), $data->getKey(), $value);
        }

        if (!is_string($value) && $value !== null) {
            $value = json_encode($value);
        }

        yield $this->storageName => $value;
    }

    public function parse(string $root, ShopContext $context): string
    {
        $keys = $context->getContextRules();

        $defaultCurrencyId = Defaults::CURRENCY;
        $currencyId = $context->getCurrencyId();

        $select = [];
        foreach ($keys as $key) {
            $field = sprintf('`%s`.`%s`', $root, $this->getStorageName());
            $path = sprintf('$.optimized.r%s.c%s.gross', $key, $currencyId);
            $select[] = sprintf('JSON_UNQUOTE(JSON_EXTRACT(%s, "%s"))', $field, $path);

            if ($context->getCurrencyId() !== Defaults::CURRENCY) {
                $path = sprintf('$.optimized.r%s.c%s.gross', $key, $defaultCurrencyId);
                $select[] = sprintf('JSON_UNQUOTE(JSON_EXTRACT(%s, "%s")) * %s', $field, $path, $context->getCurrencyFactor());
            }
        }

        $select[] = sprintf('`%s`.`%s`->"$.price.gross"', $root, $this->getStorageName());

        return sprintf('(CAST(COALESCE(%s) AS DECIMAL))', implode(',', $select));
    }

    public static function format(string $ruleId, string $currencyId, float $gross, float $net)
    {
        return [
            'r' . $ruleId => [
                'c' . $currencyId => ['gross' => $gross, 'net' => $net],
            ],
        ];
    }

    public function convertToStorage($data): array
    {
        $queryOptimized = [];
        foreach ($data as $row) {
            $queryOptimized = array_merge_recursive(
                $queryOptimized,
                self::format(
                    $row['contextRuleId'],
                    $row['currencyId'],
                    $row['price']['gross'],
                    $row['price']['net']
                )
            );
        }

        return [
            'raw' => $data,
            'optimized' => $queryOptimized,
        ];
    }
}
