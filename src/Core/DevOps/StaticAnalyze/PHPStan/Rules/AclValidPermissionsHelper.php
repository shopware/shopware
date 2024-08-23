<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class AclValidPermissionsHelper
{
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
     * @var array<string>
     */
    private array $permissions = [];

    public function __construct(string $schemaPath = self::SCHEMA_FILE)
    {
        $this->permissions = $this->preparePermissions($schemaPath);
        if ($this->permissions === []) {
            throw new \RuntimeException('Could not load permissions from schema');
        }
    }

    public function aclKeyValid(string $key): bool
    {
        return \in_array($key, $this->permissions, true);
    }

    /**
     * @return array<string>
     */
    private function preparePermissions(string $schemaPath): array
    {
        $entities = $this->getEntitiesFromSchema($schemaPath);
        if ($entities === null) {
            return [];
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
        if (!file_exists($path)) {
            return null;
        }

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
