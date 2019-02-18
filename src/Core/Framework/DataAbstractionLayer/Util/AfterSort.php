<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class AfterSort
{
    public static function sort(array $elements, string $propertyName = 'afterId'): array
    {
        if (!$elements) {
            return $elements;
        }

        // pre-sort elements to pull elements without an after id parent to the front
        uasort($elements, function (Entity $a, Entity $b) use ($propertyName) {
            if ($a->get($propertyName) === $b->get($propertyName) && $a->get($propertyName) === null) {
                return 0;
            }

            if ($a->get($propertyName) === null) {
                return -1;
            }

            if ($b->get($propertyName) === null) {
                return 1;
            }

            return 0;
        });

        // add first element to sorted list as this will be the absolute first item
        $sorted = [array_shift($elements)];
        $lastId = $sorted[0]->getId();

        while (count($elements) > 0) {
            foreach ($elements as $index => $element) {
                if ($element->get($propertyName) !== $lastId) {
                    continue;
                }

                // find the next element in the chain and set it as the new parent
                $sorted[] = $element;
                $lastId = $element->getId();
                unset($elements[$index]);

                // skip the last part of the while loop which handles an invalid chain
                continue 2;
            }

            // chain is broken, continue with next element as parent
            $nextItem = array_shift($elements);
            $sorted[] = $nextItem;

            if (!count($elements)) {
                break;
            }

            $lastId = $nextItem->get($propertyName);
        }

        return $sorted;
    }
}
