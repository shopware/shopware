<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search\Query;

use Shopware\Api\Entity\Search\CriteriaPartInterface;
use Shopware\Framework\Struct\Struct;

abstract class Query extends Struct implements CriteriaPartInterface
{
}
