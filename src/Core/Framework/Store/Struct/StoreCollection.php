<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 *
 * @template TElement of StoreStruct
 *
 * @template-extends Collection<TElement>
 */
#[Package('checkout')]
abstract class StoreCollection extends Collection
{
    /**
     * @param array<TElement|array<string, mixed>> $elements
     */
    public function __construct(iterable $elements = [])
    {
        foreach ($elements as $element) {
            if (\is_array($element)) {
                $element = $this->getElementFromArray($element);
            }

            $this->add($element);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will only return string
     */
    protected function getExpectedClass(): ?string
    {
        /** @phpstan-ignore return.type (The StoreStruct class is used as fallback. Typically, there is a dedicated StoreStruct class) */
        return StoreStruct::class;
    }

    /**
     * @param array<string, mixed> $element
     *
     * @return TElement
     */
    abstract protected function getElementFromArray(array $element): StoreStruct;
}
