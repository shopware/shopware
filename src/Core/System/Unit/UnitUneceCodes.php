<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class UnitUneceCodes
{
    /**
     * @var list<array{code: string, name: string}>|null
     */
    private ?array $codes = null;

    /**
     * @var array<array-key, true>|null
     */
    private ?array $lookup = null;

    /**
     * @return list<array{code: string, name: string}>
     */
    public function getCodes(): array
    {
        if ($this->codes !== null) {
            return $this->codes;
        }

        $codes = [];
        foreach ((new \ReflectionClass(ZugferdUnitCodes::class))->getConstants() as $name => $code) {
            if (!\is_string($code) || !preg_match('/^REC2[01]_(.+)$/', $name, $match)) {
                continue;
            }

            $codes[$code] ??= ['code' => $code, 'name' => strtolower(str_replace('_', ' ', $match[1]))];
        }

        return $this->codes = array_values($codes);
    }

    public function isValid(string $code): bool
    {
        $this->lookup ??= array_fill_keys(array_column($this->getCodes(), 'code'), true);

        return isset($this->lookup[$code]);
    }
}
