<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type;

use Shopware\Core\Framework\Struct\Struct;

class FieldOperator extends Struct
{
    /** @var string */
    protected $identifier;

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }
}