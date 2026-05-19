<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @phpstan-ignore-next-line cannot be final, as it is extended, also designed to be used directly
 */
#[Package('framework')]
class UpdateCommand extends WriteCommand implements ChangeSetAware
{
    use ChangeSetAwareTrait;

    /**
     * @var array<string>
     *
     * @description List of fields in storage format that are immutable and have been changed in this update command
     */
    private array $immutableFieldsChanges = [];

    public function getPrivilege(): ?string
    {
        return AclRoleDefinition::PRIVILEGE_UPDATE;
    }

    /**
     * @return array<string>
     */
    public function getImmutableFieldsChanges(): array
    {
        return $this->immutableFieldsChanges;
    }

    /**
     * @param array<string> $immutableFieldsChanges
     */
    public function setImmutableFieldsChanges(array $immutableFieldsChanges): void
    {
        $this->immutableFieldsChanges = $immutableFieldsChanges;
    }
}
