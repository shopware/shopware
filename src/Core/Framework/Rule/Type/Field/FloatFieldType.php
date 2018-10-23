<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type\Field;

class FloatFieldType extends FieldType
{
    const IDENTIFIER = 'decimal';

    public function __construct(FieldOperator... $operators)
    {
        $this->identifier = self::IDENTIFIER;
        $this->operators[] = $operators;
    }
}