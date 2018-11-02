<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

class FieldExtenderCollection extends FieldExtender
{
    /**
     * @var FieldExtender[]
     */
    private $fieldExtenders = [];

    public function addExtender(FieldExtender $extender): void
    {
        $this->fieldExtenders[] = $extender;
    }

    public function extend(Field $field): void
    {
        foreach ($this->fieldExtenders as $fieldExtender) {
            $fieldExtender->extend($field);
        }

        $field->setFieldExtenderCollection($this);
    }
}
