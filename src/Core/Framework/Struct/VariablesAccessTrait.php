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
        // lazy ghost entities of partial loads: only expose the loaded properties
        if (LazyObjectVars::isUninitialized($this)) {
            return LazyObjectVars::extract($this);
        }

        return get_object_vars($this);
    }
}
