<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Api2\WriteContext;

interface WriteContextAware
{
    public function setWriteContext(WriteContext $writeContext): void;
}