<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

abstract class AssociationField extends Field
{
    protected string $reference;

    protected bool $inherited = false;

    /**
     * set-null|cascade|restrict
     */
    protected string $onDelete;

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getInherited(): bool
    {
        return $this->inherited;
    }

    public function getOnDelete(): string
    {
        return $this->onDelete;
    }
}
