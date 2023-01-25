<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
interface ChangeSetAware
{
    /**
     * Used to check whether a change set should be determined for this command.
     */
    public function requiresChangeSet(): bool;

    /**
     * Allows you to request the change set for this command. The change set is then calculated before executing the
     * command and is then available in the `EntityWriteResult` or in the `PostWriteValidationEvent`.
     */
    public function requestChangeSet(): void;

    /**
     * Returns the determined change set as soon as it has been calculated.
     */
    public function getChangeSet(): ?ChangeSet;

    public function setChangeSet(?ChangeSet $changeSet): void;
}
