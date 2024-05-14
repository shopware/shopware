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

    protected function getExpectedClass(): ?string
    {
        /** @phpstan-ignore-next-line PHPStan somehow thinks the class constant is a string and not a class-string like declared in the parent */
        return ExtensionStruct::class;
    }

    /**
     * @param array<string, mixed> $element
     *
     * @return TElement
     */
    abstract protected function getElementFromArray(array $element): StoreStruct;
}
