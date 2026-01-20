<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-ignore-next-line cannot be final, as it is extended, also designed to be used directly
 */
#[Package('framework')]
class UpdateCommand extends WriteCommand implements ChangeSetAware
{
    use ChangeSetAwareTrait;

    /**
     * @var array<string>
     */
    private array $immutableChanges = [];

    public function getPrivilege(): ?string
    {
        return AclRoleDefinition::PRIVILEGE_UPDATE;
    }

    /**
     * @return array<string>
     */
    public function getImmutableChanges(): array
    {
        return $this->immutableChanges;
    }

    /**
     * @param array<string> $immutableChanges
     */
    public function setImmutableChanges(array $immutableChanges): void
    {
        $this->immutableChanges = $immutableChanges;
    }
}
