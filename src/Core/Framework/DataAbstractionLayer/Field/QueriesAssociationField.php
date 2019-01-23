<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

class QueriesAssociationField extends ChildrenAssociationField
{
    public function __construct(string $referenceClass)
    {
        parent::__construct($referenceClass);
        $this->setPropertyName('queries');
    }
}
