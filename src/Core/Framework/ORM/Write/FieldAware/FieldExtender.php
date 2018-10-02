<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\FieldAware;

use Shopware\Core\Framework\ORM\Field\Field;

abstract class FieldExtender
{
    abstract public function extend(Field $field): void;
}
