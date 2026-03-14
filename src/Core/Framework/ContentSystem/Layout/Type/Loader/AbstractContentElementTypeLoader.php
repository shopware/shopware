<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Loader;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractContentElementTypeLoader
{
    /**
     * @return list<ContentElementTypeSpecification>
     */
    abstract public function load(): array;
}
