<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AclValidPermissionsHelper
{
    public const INVALID_KEY_ERROR_MESSAGE = 'Permission "%s" is not a valid backend ACL key. If it\'s an entity based permission, please check if entity is listed in the entity-schema.json. If it\'s a custom permissions, please check if it should be added to the allowlist.';

    public const MISSING_SCHEMA_ERROR_MESSAGE = 'The entity-schema.json file is missing. Please make sure to generate it first via the administration command. Could not look up permission "%s" in the schema.';

    private const SCHEMA_FILE = __DIR__ . '/../../../../../Administration/Resources/app/administration/test/_mocks_/entity-schema.json';

    private const CUSTOM_PERMISSIONS = [
        'api_action_access-key_integration',
        'api_acl_privileges_get',
        'api_acl_privileges_additional_get',
        'system:cache:info',
        'api_action_cache_index',
        'system:clear:cache',
        'api_feature_flag_toggle',
        'api_proxy_switch-customer',
        'api_proxy_imitate-customer',
        'system.plugin_maintain',
        'system.plugin_upload',
        'system:core:update',
        'app',
        'api_send_email',
        'promotion.editor',
        'order_refund.editor',
        'user_change_me',
        'notification:create',
    ];

    /**
     * @var ?array<string>
     */
    private ?array $permissions = null;

    public function __construct(string $schemaPath = self::SCHEMA_FILE)
    {
        if (!file_exists($schemaPath)) {
            return;
        }

        $this->permissions = $this->preparePermissions($schemaPath);
        if ($this->permissions === null) {
            throw new \RuntimeException('Could not load permissions from entity schema');
        }
    }

    public function aclKeyValid(string $key): bool
    {
        if ($this->permissions === null) {
            throw new \RuntimeException('Entity schema file not found');
        }

        return \in_array($key, $this->permissions, true);
    }

    /**
     * @return ?array<string>
     */
    private function preparePermissions(string $schemaPath): ?array
    {
        $entities = $this->getEntitiesFromSchema($schemaPath);
        if ($entities === null) {
            return null;
        }

        $permissions = [];
        foreach ($entities as $entity) {
            $permissions[] = $entity . ':read';
            $permissions[] = $entity . ':create';
            $permissions[] = $entity . ':update';
            $permissions[] = $entity . ':delete';
        }

        return array_merge($permissions, self::CUSTOM_PERMISSIONS);
    }

    /**
     * @return array<string>|null
     */
    private function getEntitiesFromSchema(string $path): ?array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $schema = json_decode($content, true);

        if (!\is_array($schema)) {
            return null;
        }

        return array_keys($schema);
    }
}
