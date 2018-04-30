<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search\Aggregation;

use Shopware\Api\Entity\Search\CriteriaPartInterface;

interface Aggregation extends CriteriaPartInterface
{
    public function getField(): string;

    public function getName(): string;
}
