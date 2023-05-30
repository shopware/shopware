<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class AfterSort
{
    public static function sort(array $elements, string $propertyName = 'afterId'): array
    {
        if (!$elements) {
            return $elements;
        }

        // NEXT-21735 - This is covered randomly
        // @codeCoverageIgnoreStart

        // pre-sort elements to pull elements without an after id parent to the front
        uasort($elements, function (Struct $a, Struct $b) use ($propertyName) {
            $aValue = $a->$propertyName; /* @phpstan-ignore-line */
            $bValue = $b->$propertyName; /* @phpstan-ignore-line */
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

        $sorted = [$first->getId() => $first];

        $lastId = $first->getId();

        while (\count($elements) > 0) {
            foreach ($elements as $index => $element) {
                if ($lastId !== $element->$propertyName) {  /* @phpstan-ignore-line */
                    continue;
                }

                // find the next element in the chain and set it as the new parent
                $sorted[$element->getId()] = $element;
                $lastId = $element->getId();
                unset($elements[$index]);

                // skip the last part of the while loop which handles an invalid chain
                continue 2;
            }

            // chain is broken, continue with next element as parent
            $nextItem = array_shift($elements);
            $sorted[$nextItem->getId()] = $nextItem;

            if (!\count($elements)) {
                break;
            }

            $lastId = $nextItem->$propertyName; /* @phpstan-ignore-line */
        }

        return $sorted;
    }
}
