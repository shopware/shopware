<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field\Traits;

trait InheritedTrait
{
    protected bool $inherited = false;

    public function getInherited(): bool
    {
        return $this->inherited;
    }
}
