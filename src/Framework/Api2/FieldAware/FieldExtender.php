<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Api2\Field\Field;

abstract class FieldExtender
{
    abstract public function extend(Field $field): void;
}