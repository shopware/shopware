<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

use Shopware\Core\Framework\Struct\Collection;

class ErrorCollection extends Collection
{
    /**
     * @param Error $error
     */
    public function add($error): void
    {
        $this->set($error->getKey(), $error);
    }

    public function blockOrder(): bool
    {
        /** @var Error $error */
        foreach ($this->elements as $error) {
            if ($error->blockOrder()) {
                return true;
            }
        }

        return false;
    }

    public function hasLevel(int $errorLevel): bool
    {
        /** @var Error $element */
        foreach ($this->elements as $element) {
            if ($element->getLevel() === $errorLevel) {
                return true;
            }
        }

        return false;
    }

    protected function getExpectedClass(): ?string
    {
        return Error::class;
    }
}
