<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

class ParentFkField extends FkField
{
    public function __construct(string $referenceClass)
    {
        parent::__construct('parent_id', 'parentId', $referenceClass);
    }
}
