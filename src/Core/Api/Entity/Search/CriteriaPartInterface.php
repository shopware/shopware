<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

interface CriteriaPartInterface
{
    public function getFields(): array;
}
