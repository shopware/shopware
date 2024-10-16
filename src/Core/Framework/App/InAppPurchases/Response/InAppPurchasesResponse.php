<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\InAppPurchases\Response;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('checkout')]
class InAppPurchasesResponse extends Struct
{
    /**
     * @var array<int, string>
     */
    protected array $purchases = [];

    /**
     * @return array<int, string>
     */
    public function getPurchases(): array
    {
        return $this->purchases;
    }

    /**
     * @param array<int, string> $purchases
     */
    public function setPurchases(array $purchases): void
    {
        $this->purchases = $purchases;
    }
}
