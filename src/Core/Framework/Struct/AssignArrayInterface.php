<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface AssignArrayInterface
{
    /**
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $fallbackSorting will be added
     * @deprecated tag:v6.8.0 - reason:return-type-change - will use "strong" return type `self`
     *
     * @param array<array-key, mixed> $options
     *
     * @return $this
     */
    public function assign(array $options/* , bool $deep = false */)/* : self */;
}
