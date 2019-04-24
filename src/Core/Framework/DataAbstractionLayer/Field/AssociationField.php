<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class AssociationField extends Field
{
    /**
     * @var string
     */
    protected $referenceClass;

    /**
     * @var bool
     */
    protected $autoload = false;

    /**
     * @return string|EntityDefinition
     */
    public function getReferenceClass(): string
    {
        return $this->referenceClass;
    }

    final public function getAutoload(): bool
    {
        return $this->autoload;
    }

    public function setReferenceClass(string $referenceClass): void
    {
        $this->referenceClass = $referenceClass;
    }
}
