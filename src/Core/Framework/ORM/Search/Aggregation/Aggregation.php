<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search\Aggregation;

use Shopware\Framework\ORM\Search\CriteriaPartInterface;

interface Aggregation extends CriteriaPartInterface
{
    public function getField(): string;

    public function getName(): string;
}
