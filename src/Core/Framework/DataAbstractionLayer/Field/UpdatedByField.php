<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedByFieldSerializer;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserDefinition;

#[Package('framework')]
class UpdatedByField extends FkField
{
    /**
     * @var array<string>
     */
    private readonly array $allowedWriteScopes;

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-default-change - omitting $allowedWriteScopes will default to [Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE]
     *
     * @param list<string> $allowedWriteScopes
     */
    public function __construct(array $allowedWriteScopes = [Context::SYSTEM_SCOPE])
    {
        parent::__construct('updated_by_id', 'updatedById', UserDefinition::class);

        if (\func_num_args() === 0 && Feature::isActive('v6.8.0.0')) {
            $allowedWriteScopes = [Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE];
        }

        if (\func_num_args() === 0 && !Feature::isActive('v6.8.0.0') && !EnvironmentHelper::getVariable('TESTS_RUNNING')) {
            trigger_deprecation(
                'shopware/core',
                '',
                \sprintf(
                    'Not passing $allowedWriteScopes to %s::__construct() will include Context::CRUD_API_SCOPE by default in v6.8.0. Pass the desired scopes explicitly to keep the current behavior.',
                    self::class
                )
            );
        }

        $this->allowedWriteScopes = $allowedWriteScopes;
    }

    /**
     * @return list<string>
     */
    public function getAllowedWriteScopes(): array
    {
        return $this->allowedWriteScopes;
    }

    protected function getSerializerClass(): string
    {
        return UpdatedByFieldSerializer::class;
    }
}
