<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
trait VariablesAccessTrait
{
    /**
     * @return array<string, mixed>
     */
    public function getVars(): array
    {
        return get_object_vars($this);
    }
}
