<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

interface Aggregation extends CriteriaPartInterface
{
    public function getField(): string;

    public function getName(): string;

    /**
     * @return string[]
     */
    public function getGroupByFields(): array;

    public function getFilter(): ?MultiFilter;
}
