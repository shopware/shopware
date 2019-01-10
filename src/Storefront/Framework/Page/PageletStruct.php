<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Framework\Struct\Struct;

class PageletStruct extends Struct
{
    /**
     * @var bool
     */
    protected $defered = false;

    /**
     * @return bool
     */
    public function isDefered(): bool
    {
        return $this->defered;
    }

    /**
     * @param bool $defered
     */
    public function setDefered(bool $defered): void
    {
        $this->defered = $defered;
    }
}
