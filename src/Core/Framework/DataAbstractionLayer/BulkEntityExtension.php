<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class BulkEntityExtension
{
    /**
     * @return \Generator<string, Field[]>
     */
    abstract public function collect(): \Generator;
}
