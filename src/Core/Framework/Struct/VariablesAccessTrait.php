<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
trait VariablesAccessTrait
{
    public function getVars(): array
    {
        return get_object_vars($this);
    }
}
