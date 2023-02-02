<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * In case the referenced association data will be deleted, the related data will be set to null and an Written event will be thrown
 */
class SetNullOnDelete extends Flag
{
    private bool $enforcedByConstraint;

    /**
     * In some cases (e.g. because of circular references) it may not be possible to enforce the SetNullBehaviour by a DB constraint,
     * in that case provide $enforcedByConstraint=false so the set null will be handled by the DAL instead
     */
    public function __construct(bool $enforcedByConstraint = true)
    {
        $this->enforcedByConstraint = $enforcedByConstraint;
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
