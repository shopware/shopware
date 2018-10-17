<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type;

use Shopware\Core\Framework\Struct\Struct;

class Field extends Struct
{
    /** @var string */
    protected $identifier;

    /** @var bool */
    protected $required;

    /** @var FieldType */
    protected $type;
}