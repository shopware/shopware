<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 */
class LicenseCollection extends Collection
{
    /**
     * @var int
     */
    protected $total = 0;

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    protected function getExpectedClass(): ?string
    {
        return LicenseStruct::class;
    }
}
