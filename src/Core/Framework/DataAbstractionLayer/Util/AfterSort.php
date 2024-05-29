<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class AfterSort
{
    /**
     * @template TElement of Struct
     *
     * @param array<array-key, TElement> $elements
     *
     * @return array<array-key, TElement>
     */
    public static function sort(array $elements, string $propertyName = 'afterId'): array
    {
        if (!$elements) {
            return $elements;
        }

        // NEXT-21735 - This is covered randomly
        // @codeCoverageIgnoreStart

        // pre-sort elements to pull elements without an after id parent to the front
        uasort($elements, function (Struct $a, Struct $b) use ($propertyName) {
            $aValue = $a->$propertyName;
            $bValue = $b->$propertyName;
            if ($aValue === $bValue && $aValue === null) {
                return 0;
            }

            if ($aValue === null) {
                return -1;
            }

            if ($bValue === null) {
                return 1;
            }

            return 0;
        });
        // @codeCoverageIgnoreEnd

        // add first element to sorted list as this will be the absolute first item
        $first = array_shift($elements);

        $acc = null;
        if (method_exists($first, 'getId')) {
            $acc = function ($item) {
                // @phpstan-ignore-next-line  (ensured via method_exists)
                return $item->getId();
            };
        } elseif (property_exists($first, 'id')) {
            $acc = function ($item) {
                // @phpstan-ignore-next-line  (ensured via method_exists)
                return $item->id;
            };
        }

        if ($acc === null) {
            return $elements;
        }

        $sorted = [$acc($first) => $first];

        $lastId = $acc($first);

        while (\count($elements) > 0) {
            foreach ($elements as $index => $element) {
                if ($lastId !== $element->$propertyName) {
                    continue;
                }

                // find the next element in the chain and set it as the new parent
                $sorted[$acc($element)] = $element;
                $lastId = $acc($element);
                unset($elements[$index]);

                // skip the last part of the while loop which handles an invalid chain
                continue 2;
            }

            // chain is broken, continue with next element as parent
            $nextItem = array_shift($elements);
            if ($nextItem) {
                $sorted[$acc($nextItem)] = $nextItem;
            }

            if (!\count($elements)) {
                break;
            }

            $lastId = $nextItem->$propertyName;
        }

        return $sorted;
    }
}
