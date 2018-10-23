<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type\Field;

use Shopware\Core\Framework\Struct\Struct;

class Field extends Struct
{
    /** @var string */
    protected $identifier;

    /** @var bool */
    protected $required;

    /** @var FieldType */
    protected $type;

    public function __construct(string $identifier, bool $required, FieldType $type)
    {
        $this->identifier = $identifier;
        $this->required = $required;
        $this->type = $type;
    }
}