<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Aggregation;

use Shopware\Core\Framework\ORM\Search\CriteriaPartInterface;

interface Aggregation extends CriteriaPartInterface
{
    public function getField(): string;

    public function getName(): string;
}
