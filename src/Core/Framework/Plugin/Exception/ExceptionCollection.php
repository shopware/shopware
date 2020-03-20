<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                       add(ShopwareHttpException $entity)
 * @method void                       set(string $key, ShopwareHttpException $entity)
 * @method ShopwareHttpException[]    getIterator()
 * @method ShopwareHttpException[]    getElements()
 * @method ShopwareHttpException|null get(string $key)
 * @method ShopwareHttpException|null first()
 * @method ShopwareHttpException|null last()
 */
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
