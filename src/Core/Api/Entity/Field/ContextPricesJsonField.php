<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Entity\Write\DataStack\KeyValuePair;
use Shopware\Api\Entity\Write\EntityExistence;

class ContextPricesJsonField extends JsonObjectField
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
