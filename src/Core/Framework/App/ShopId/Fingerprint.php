<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface Fingerprint
{
    public function getIdentifier(): string;

    public function getScore(): int;

    public function getStamp(): string;
}
