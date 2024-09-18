<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Error;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Util\Hasher;

/**
 * @extends Collection<Error>
 */
#[Package('checkout')]
class ErrorCollection extends Collection
{
    /**
     * @param Error $error
     */
    public function add($error): void
    {
        $this->set($error->getId(), $error);
    }

    public function blockOrder(): bool
    {
        foreach ($this->getIterator() as $error) {
            if ($error->blockOrder()) {
                return true;
            }
        }

        return false;
    }

    public function blockResubmit(): bool
    {
        foreach ($this->getIterator() as $error) {
            if ($error->blockResubmit()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<array-key, Error>
     */
    public function getErrors(): array
    {
        return $this->filterByErrorLevel(Error::LEVEL_ERROR);
    }

    /**
     * @return array<array-key, Error>
     */
    public function getWarnings(): array
    {
        return $this->filterByErrorLevel(Error::LEVEL_WARNING);
    }

    /**
     * @return array<array-key, Error>
     */
    public function getNotices(): array
    {
        return $this->filterByErrorLevel(Error::LEVEL_NOTICE);
    }

    public function getPersistent(): self
    {
        return $this->filter(fn (Error $error) => $error->isPersistent());
    }

    /**
     * @return array<array-key, Error>
     */
    public function filterByErrorLevel(int $errorLevel): array
    {
        return $this->fmap(static fn (Error $error): ?Error => $errorLevel === $error->getLevel() ? $error : null);
    }

    public function hasLevel(int $errorLevel): bool
    {
        foreach ($this->getIterator() as $element) {
            if ($element->getLevel() === $errorLevel) {
                return true;
            }
        }

        return false;
    }

    public function getApiAlias(): string
    {
        return 'cart_error_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return Error::class;
    }

    public function getUniqueHash(): string
    {
        if ($this->elements === []) {
            return '';
        }

        $hash = '';

        foreach ($this->elements as $element) {
            $hash .= $element->getId() . json_encode($element->getParameters());
        }

        return Hasher::hash($hash, 'xxh64');
    }
}
