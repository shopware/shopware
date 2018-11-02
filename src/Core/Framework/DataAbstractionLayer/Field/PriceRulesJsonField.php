<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class PriceRulesJsonField extends JsonField
{
    public static function format(string $ruleId, string $currencyId, float $gross, float $net): array
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
                    $row['ruleId'],
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

    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
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

        if (!\is_string($value) && $value !== null) {
            $value = json_encode($value);
        }

        yield $this->storageName => $value;
    }
}
