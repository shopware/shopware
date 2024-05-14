<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-ignore-next-line cannot be final, as it is extended, also designed to be used directly
 */
#[Package('core')]
class DeleteCommand extends WriteCommand implements ChangeSetAware
{
    use ChangeSetAwareTrait;

    /**
     * @deprecated tag:v6.7.0 - Property will be removed
     */
    protected EntityDefinition $definition;

    /**
     * @param array<string, string> $primaryKey
     */
    public function __construct(
        EntityDefinition $definition,
        array $primaryKey,
        EntityExistence $existence
    ) {
        $this->definition = $definition;

        parent::__construct($definition, [], $primaryKey, $existence, '');
    }

    public function isValid(): bool
    {
        return (bool) \count($this->primaryKey);
    }

    public function getPrivilege(): ?string
    {
        return AclRoleDefinition::PRIVILEGE_DELETE;
    }

    /**
     * @deprecated tag:v6.7.0 - Method will be removed
     */
    public function getDefinition(): EntityDefinition
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getDefinition', 'v6.7.0.0')
        );

        return $this->definition;
    }
}
