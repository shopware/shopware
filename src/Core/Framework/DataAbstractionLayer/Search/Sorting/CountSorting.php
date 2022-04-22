<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting;

class CountSorting extends FieldSorting
{
    protected string $type = 'count';
}
