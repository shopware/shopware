<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Loader;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractContentSystemElementTypeLoader
{
    /**
     * @return list<ContentSystemElementTypeSpecification>
     */
    abstract public function load(): array;
}
