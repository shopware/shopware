<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;

class ChildrenAssociationField extends OneToManyAssociationField
{
    public function __construct(string $referenceClass, string $propertyName = 'children')
    {
        parent::__construct($propertyName, $referenceClass, 'parent_id');
        $this->addFlags(new CascadeDelete());
    }
}
