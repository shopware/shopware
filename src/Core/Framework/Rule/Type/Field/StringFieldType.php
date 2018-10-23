<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type\Field;

class StringFieldType extends FieldType
{
    const IDENTIFIER = 'text';

    public function __construct(FieldOperator... $operators)
    {
        $this->identifier = self::IDENTIFIER;
        $this->operators[] = $operators;
    }
}