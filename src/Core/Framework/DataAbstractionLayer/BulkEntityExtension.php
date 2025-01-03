<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class BulkEntityExtension
{
    /**
     * Constructor is final to ensure the extensions can be built without any dependencies
     */
    final public function __construct()
    {
    }

    /**
     * @return \Generator<string, list<Field>>
     */
    abstract public function collect(): \Generator;
}
