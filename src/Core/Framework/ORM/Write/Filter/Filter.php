<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\Filter;

interface Filter
{
    public function filter($value);
}
