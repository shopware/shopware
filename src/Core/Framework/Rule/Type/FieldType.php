<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type;

use Shopware\Core\Framework\Struct\Struct;

abstract class FieldType extends Struct
{
    /** @var string */
    protected $identifier;

    /** @var FieldOperator[] */
    protected $operators;
}