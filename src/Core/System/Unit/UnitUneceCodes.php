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
     * @var array<string|int, string>|null
     */
    private ?array $codes = null;

    /**
     * @return array<string|int, string>
     */
    public function getCodes(): array
    {
        if ($this->codes !== null) {
            return $this->codes;
        }

        $codes = [];
        foreach ((new \ReflectionClass(ZugferdUnitCodes::class))->getConstants() as $name => $code) {
            if (!\is_string($code) || !preg_match('/^REC2[01]_(.+)\z/', $name, $match)) {
                continue;
            }

            $codes[$code] ??= strtolower(str_replace('_', ' ', $match[1]));
        }

        return $this->codes = $codes;
    }

    public function isValid(string $code): bool
    {
        return isset($this->getCodes()[$code]);
    }
}
