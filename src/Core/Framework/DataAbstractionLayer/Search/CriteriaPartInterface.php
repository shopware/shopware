<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

interface CriteriaPartInterface
{
    public function getFields(): array;
}
