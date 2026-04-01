<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedByFieldSerializer;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserDefinition;

#[Package('framework')]
class CreatedByField extends FkField
{
    /**
     * @var array<string>
     */
    private readonly array $allowedWriteScopes;

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-default-change - $allowedWriteScopes will default to [Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE] and be not nullable again
     *
     * @param list<string>|null $allowedWriteScopes
     */
    public function __construct(?array $allowedWriteScopes = null)
    {
        parent::__construct('created_by_id', 'createdById', UserDefinition::class);

        if ($allowedWriteScopes === null) {
            if (Feature::isActive('v6.8.0.0')) {
                $allowedWriteScopes = [Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE];
            } else {
                Feature::triggerDeprecationOrThrow(
                    'v6.8.0.0',
                    \sprintf(
                        'Not passing $allowedWriteScopes to %s::__construct() will include Context::CRUD_API_SCOPE by default in v6.8.0. Pass the desired scopes explicitly to keep the current behavior.',
                        self::class
                    )
                );

                $allowedWriteScopes = [Context::SYSTEM_SCOPE];
            }
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
        return CreatedByFieldSerializer::class;
    }
}
