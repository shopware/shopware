<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Framework\Struct\Struct;

class PageStruct extends Struct
{
    public function attach(PageletStruct $pageletData): PageStruct
    {
        foreach (get_object_vars($this) as $propertyName => $property) {
            if ($property instanceof $pageletData) {
                $methodeName = 'set' . ucfirst($propertyName);
                $this->$methodeName($pageletData);
            }
        }

        return $this;
    }
}
