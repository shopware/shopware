<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type\Field;

class IntFieldType extends FieldType
{
    const IDENTIFIER = 'number';

    public function __construct(FieldOperator... $operators)
    {
        $this->identifier = self::IDENTIFIER;
        $this->operators[] = $operators;
    }
}