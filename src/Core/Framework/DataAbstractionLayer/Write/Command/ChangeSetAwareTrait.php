<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
trait ChangeSetAwareTrait
{
    /**
     * @var bool
     */
    protected $requireChangeSet = false;

    /**
     * @var ChangeSet|null
     */
    protected $changeSet;

    public function requiresChangeSet(): bool
    {
        return $this->requireChangeSet;
    }

    public function requestChangeSet(): void
    {
        $this->requireChangeSet = true;
    }

    public function getChangeSet(): ?ChangeSet
    {
        return $this->changeSet;
    }

    public function setChangeSet(?ChangeSet $changeSet): void
    {
        $this->changeSet = $changeSet;
    }
}
