<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
abstract class AssociationField extends Field
{
    public const SET_NULL = 'set-null';
    public const CASCADE = 'cascade';
    public const RESTRICT = 'restrict';

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
