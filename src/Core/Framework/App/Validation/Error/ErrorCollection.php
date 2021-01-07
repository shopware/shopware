<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal only for use by the app-system
 *
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
        $this->set($error->getMessageKey(), $error);
    }

    public function addErrors(ErrorCollection $errors): void
    {
        foreach ($errors as $error) {
            $this->set($error->getMessageKey(), $error);
        }
    }

    protected function getExpectedClass(): ?string
    {
        return Error::class;
    }
}
