<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

class ParentField extends FkField
{
    public function __construct(string $referenceClass)
    {
        parent::__construct('parent_id', 'parentId', $referenceClass, 'id');
        $this->referenceClass = $referenceClass;
    }
}
