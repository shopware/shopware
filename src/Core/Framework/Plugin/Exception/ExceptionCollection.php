<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Struct\Collection;

class ExceptionCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return ShopwareHttpException::class;
    }
}
