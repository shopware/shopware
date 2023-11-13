<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * In case the referenced association data will be deleted, the related data will be set to null and an Written event will be thrown
 */
#[Package('core')]
class SetNullOnDelete extends Flag
{
    /**
     * In some cases (e.g. because of circular references) it may not be possible to enforce the SetNullBehaviour by a DB constraint,
     * in that case provide $enforcedByConstraint=false so the set null will be handled by the DAL instead
     */
    public function __construct(private readonly bool $enforcedByConstraint = true)
    {
    }

    public function isEnforcedByConstraint(): bool
    {
        return $this->enforcedByConstraint;
    }

    public function parse(): \Generator
    {
        yield 'set_null_on_delete' => true;
    }
}
