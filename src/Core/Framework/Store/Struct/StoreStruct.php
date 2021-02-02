<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
abstract class StoreStruct extends Struct
{
    abstract public static function fromArray(array $data): self;
}
