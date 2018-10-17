<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type;

use Shopware\Core\Framework\Struct\Struct;

class RuleTypeStruct extends Struct
{
    /** @var Field[] */
    protected $fields;

    /** @var string */
    protected $label;

    /** @var string */
    protected $type;
}