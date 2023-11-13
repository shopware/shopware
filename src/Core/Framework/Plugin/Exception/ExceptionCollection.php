<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ShopwareHttpException>
 */
#[Package('core')]
class ExceptionCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'plugin_exception_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return ShopwareHttpException::class;
    }
}
