<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting;

use Shopware\Core\Framework\Log\Package;
/**
 * @final tag:v6.5.0
 * @package core
 */
#[Package('core')]
class CountSorting extends FieldSorting
{
    protected string $type = 'count';
}
