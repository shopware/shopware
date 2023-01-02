<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal only for use by the app-system
 *
 * @extends Collection<Error>
 */
#[Package('core')]
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
