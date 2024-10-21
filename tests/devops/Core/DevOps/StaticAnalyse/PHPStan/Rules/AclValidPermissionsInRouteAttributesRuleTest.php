<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\AclValidPermissionsHelper;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\AclValidPermissionsInRouteAttributesRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<AclValidPermissionsInRouteAttributesRule>
 */
#[CoversClass(AclValidPermissionsInRouteAttributesRule::class)]
class AclValidPermissionsInRouteAttributesRuleTest extends RuleTestCase
{
    private static ?AclValidPermissionsInRouteAttributesRule $rule = null;

    public static function setUpBeforeClass(): void
    {
        self::$rule = new AclValidPermissionsInRouteAttributesRule(new AclValidPermissionsHelper(__DIR__ . '/data/AclValidPermissionsRule/entity-schema.json'));
    }

    public static function tearDownAfterClass(): void
    {
        self::$rule = null;
    }

    #[RunInSeparateProcess]
    public function testRule(): void
    {
        // route attribute in controller
        $this->analyse([__DIR__ . '/data/AclValidPermissionsRule/invalid-acl-name-in-route-attribute.php'], [
            [
                'Permission "class-non-existing-permission" is not a valid backend ACL key. If it\'s an entity based permission, please check if entity is listed in the entity-schema.json. If it\'s a custom permissions, please check if it should be added to the allowlist.',
                6,
            ],
            [
                'Permission "system:create" is not a valid backend ACL key. If it\'s an entity based permission, please check if entity is listed in the entity-schema.json. If it\'s a custom permissions, please check if it should be added to the allowlist.',
                9,
            ],
            [
                'Permission "non-existing-permission" is not a valid backend ACL key. If it\'s an entity based permission, please check if entity is listed in the entity-schema.json. If it\'s a custom permissions, please check if it should be added to the allowlist.',
                9,
            ],
        ]);

        // attribute in non-controller - skip
        $this->analyse([__DIR__ . '/data/AclValidPermissionsRule/skipping-route-attribute-check-in-non-controller.php'], [
        ]);

        // attributes where acl name can't be extracted - skip
        $this->analyse([__DIR__ . '/data/AclValidPermissionsRule/attributes-to-be-skipped.php'], [
        ]);
    }

    /**
     * @return AclValidPermissionsInRouteAttributesRule
     */
    protected function getRule(): Rule
    {
        \assert(self::$rule instanceof AclValidPermissionsInRouteAttributesRule);

        return self::$rule;
    }
}
