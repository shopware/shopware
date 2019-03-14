<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                          add(PriceDefinitionInterface $entity)
 * @method void                          set(string $key, PriceDefinitionInterface $entity)
 * @method PriceDefinitionInterface[]    getIterator()
 * @method PriceDefinitionInterface[]    getElements()
 * @method PriceDefinitionInterface|null first()
 * @method PriceDefinitionInterface|null last()
 */
class PriceDefinitionCollection extends Collection
{
    public function get($key): ?PriceDefinitionInterface
    {
        $key = (int) $key;

        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    protected function getExpectedClass(): ?string
    {
        return PriceDefinitionInterface::class;
    }
}
