<?php declare(strict_types=1);

namespace Shopware\Api\Search\Aggregation;

use Shopware\Api\Search\CriteriaPartInterface;

interface Aggregation extends CriteriaPartInterface
{
    public function getField(): string;

    public function getName(): string;
}
