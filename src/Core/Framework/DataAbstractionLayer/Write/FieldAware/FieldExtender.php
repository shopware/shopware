<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

abstract class FieldExtender
{
    abstract public function extend(Field $field): void;
}
