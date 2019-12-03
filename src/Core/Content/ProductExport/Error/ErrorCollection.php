<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Error;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void       set(string $key, Error $entity)
 * @method Error[]    getIterator()
 * @method Error[]    getElements()
 * @method Error|null get(string $key)
 * @method Error|null first()
 * @method Error|null last()
 */
class ErrorCollection extends Collection
{
    /**
     * @param Error $error
     */
    public function add($error): void
    {
        $this->set($error->getId(), $error);
    }

    protected function getExpectedClass(): ?string
    {
        return Error::class;
    }
}
