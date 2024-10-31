<?php

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

abstract class BulkEntityExtension
{
    /**
     * @return \Generator<string, Field[]>
     */
    abstract public function collect(): \Generator;
}
