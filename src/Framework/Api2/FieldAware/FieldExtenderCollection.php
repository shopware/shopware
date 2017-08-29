<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Shopware\Framework\Api2\Field\Field;

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
        foreach($this->fieldExtenders as $fieldExtender) {
            $fieldExtender->extend($field);
        }

        if($field instanceof FieldExtenderCollectionAware) {
            $field->setFieldExtenderCollection($this);
        }
    }
}