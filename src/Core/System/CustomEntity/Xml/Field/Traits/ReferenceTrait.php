<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field\Traits;

trait ReferenceTrait
{
    protected string $reference;

    public function getReference(): string
    {
        return $this->reference;
    }
}
