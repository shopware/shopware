<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Write\DataStack\KeyValuePair;
use Shopware\Api\Entity\Write\EntityExistence;
use Shopware\Api\Entity\Write\FieldAware\SqlParseAware;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;

class PriceRulesField extends JsonObjectField implements SqlParseAware
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

        $defaultCurrencyId = Uuid::fromString(Defaults::CURRENCY)->getHex();
        $currencyId = Uuid::fromString($context->getCurrencyId())->getHex();

        $select = [];
        foreach ($keys as $key) {
            $key = Uuid::fromString($key)->getHex();

            $select[] = sprintf('`%s`.`%s`->"$.merged.r%s.last.c%s.gross"', $root, $this->getStorageName(), $key, $currencyId);
            if ($context->getCurrencyId() !== Defaults::CURRENCY) {
                $select[] = sprintf('(`%s`.`%s`->"$.merged.r%s.last.c%s.gross") * %s', $root, $this->getStorageName(), $key, $defaultCurrencyId, $context->getCurrencyFactor());
            }
        }

        //fallback field
        $select[] = sprintf('`%s`.`%s`', $root, 'price');

        return sprintf('(CAST(COALESCE(%s) AS DECIMAL))', implode(',', $select));
    }

    public static function format(string $ruleId, string $currencyId, int $quantityStart, ?int $quantityEnd, float $gross, float $net)
    {
        $quantityKey = $quantityStart . '-' . $quantityEnd;
        if ($quantityEnd === null) {
            $quantityKey = 'last';
        } elseif ($quantityStart === 1) {
            $quantityKey = 'first';
        }

        $ruleId = Uuid::fromString($ruleId)->getHex();
        $currencyId = Uuid::fromString($currencyId)->getHex();

        return [
            'r' . $ruleId => [
                $quantityKey => [
                    'quantityStart' => $quantityStart,
                    'quantityEnd' => $quantityEnd,
                    'c' . $currencyId => ['gross' => $gross, 'net' => $net],
                ],
            ],
        ];
    }

    private function convertToStorage($data)
    {
        $queryOptimized = [];
        foreach ($data as $row) {
            $queryOptimized = array_merge_recursive(
                $queryOptimized,
                self::format(
                    $row['ruleId'],
                    $row['currencyId'],
                    $row['quantityStart'],
                    $row['quantityEnd'],
                    $row['gross'],
                    $row['net']
                )
            );
        }

        return [
            'raw' => $data,
            'merged' => $queryOptimized,
        ];
    }
}
