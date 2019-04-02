<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class AssociationField extends Field
{
    /**
     * @var bool
     */
    protected $loadInBasic = false;

    /**
     * @var string
     */
    protected $referenceClass;

    public function loadInBasic(): bool
    {
        return $this->loadInBasic;
    }

    /**
     * @return string|EntityDefinition
     */
    public function getReferenceClass(): string
    {
        return $this->referenceClass;
    }
}
