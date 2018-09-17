<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

interface Validatable
{
    public function getRule(): ? Rule;
}
