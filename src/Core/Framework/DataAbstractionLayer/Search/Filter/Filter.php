<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\Struct\Struct;

abstract class Filter extends Struct implements CriteriaPartInterface
{
}
