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

        $latestGroup = 0;
        $groups = [];
        $referenceList = [];
        foreach ($elements as $child) {
            // Find first element with null
            if (null === $child->$propertyName) {
                $groups[$latestGroup][$child->getId()] = $child;
                $referenceList[$child->getId()] = $latestGroup;
            }
            $elements[$child->getId()] = $child;
        }

        // Group elements into chain groups using $propertyName and $id as link
        foreach ($elements as $child) {
            if (isset($elements[$child->$propertyName])) {
                $parent = $elements[$child->$propertyName];
                if (false === isset($referenceList[$parent->getId()])
                    && false === isset($referenceList[$child->getId()])) {
                    // Parent and child not existing at any group - create new
                    $latestGroup++;

                    $referenceList[$child->getId()] = $latestGroup;
                    $referenceList[$parent->getId()] = $latestGroup;
                    $groups[$latestGroup] = [
                        $parent->getId() => $parent,
                        $child->getId() => $child,
                    ];
                } elseif (false === isset($referenceList[$child->getId()])) {
                    // Parent existing using in to create link
                    $referenceList[$child->getId()] = $referenceList[$parent->getId()];
                    $groups[$latestGroup][$child->getId()] = $child;
                } elseif (false === isset($referenceList[$parent->getId()])) {
                    // child existing using in to create link
                    $referenceList[$parent->getId()] = $referenceList[$child->getId()];
                    $groups[$latestGroup][$parent->getId()] = $parent;
                }
            }
        }
        //Combining group into single array, after sorting,
        $list = [];
        foreach ($groups as $groupElements) {
            $groupElements = self::basicSort($groupElements, $propertyName);
            if (null !== array_key_last($list)) {
                // Joining link between two groups
                $firstCategory = $groupElements[array_key_first($groupElements)];
                $firstCategory->$propertyName = array_key_last($list);
            }
            $list += $groupElements;
        }

        return $list;
    }

    /**
     * @template TElement of Struct
     *
     * @param array<array-key, TElement> $elements
     *
     * @return array<array-key, TElement>
     */
    public static function basicSort(array $elements, string $propertyName = 'afterId'): array
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
        if (!method_exists($first, 'getId')) {
            return $elements;
        }

        $sorted = [$first->getId() => $first];

        $lastId = $first->getId();

        while (\count($elements) > 0) {
            foreach ($elements as $index => $element) {
                if ($lastId !== $element->$propertyName) {
                    continue;
                }
                if (!method_exists($element, 'getId')) {
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
            if ($nextItem && method_exists($nextItem, 'getId')) {
                $sorted[$nextItem->getId()] = $nextItem;
            }

            if (!\count($elements)) {
                break;
            }

            $lastId = $nextItem->$propertyName;
        }

        return $sorted;
    }
}
