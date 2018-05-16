<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

interface CriteriaPartInterface
{
    public function getFields(): array;
}
