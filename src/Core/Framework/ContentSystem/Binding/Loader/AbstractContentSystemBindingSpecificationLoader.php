<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Loader;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractContentSystemBindingSpecificationLoader
{
    /**
     * @return list<BindingSpecification>
     */
    abstract public function load(): array;
}
