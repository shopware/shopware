<?php declare(strict_types=1);


namespace Shopware\Framework\Api2\FieldAware;


interface FieldExtenderCollectionAware
{
    public function setFieldExtenderCollection(FieldExtenderCollection $fieldExtenderCollection): void;
}
