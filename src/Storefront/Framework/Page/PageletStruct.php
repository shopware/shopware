<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Framework\Struct\Struct;

class PageletStruct extends Struct
{
    /**
     * @var bool
     */
    protected $deferred = false;

    /**
     * @return bool
     */
    public function isDeferred(): bool
    {
        return $this->deferred;
    }

    /**
     * @param bool $deferred
     */
    public function setDeferred(bool $deferred): void
    {
        $this->deferred = $deferred;
    }
}
