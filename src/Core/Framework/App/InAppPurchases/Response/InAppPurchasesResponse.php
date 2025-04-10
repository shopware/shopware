<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\InAppPurchases\Response;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\AssignArrayTrait;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class InAppPurchasesResponse
{
    use AssignArrayTrait;

    /**
     * @var list<string>
     */
    public array $purchases = [];
}
