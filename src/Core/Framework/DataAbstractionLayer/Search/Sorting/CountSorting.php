<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting;

/**
 * @final
 *
 * @package core
 */
class CountSorting extends FieldSorting
{
    protected string $type = 'count';
}
