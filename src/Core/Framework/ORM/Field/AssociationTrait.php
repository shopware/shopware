<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Field;

use Shopware\Framework\ORM\EntityDefinition;

trait AssociationTrait
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
