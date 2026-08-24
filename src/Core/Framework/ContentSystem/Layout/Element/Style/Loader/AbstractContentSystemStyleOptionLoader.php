<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractContentSystemStyleOptionLoader
{
    /**
     * @return list<StyleOptionSpecification>
     */
    abstract public function load(): array;
}
