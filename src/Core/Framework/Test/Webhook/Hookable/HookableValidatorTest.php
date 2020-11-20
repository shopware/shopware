<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\Hookable;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Exception\HookableValidationException;
use Shopware\Core\Framework\Webhook\Hookable\HookableValidator;

class HookableValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var HookableValidator
     */
    private $hookableValidator;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var PermissionPersister
     */
    private $permissionPersister;

    /**
     * @var string
     */
    private $roleId;

    public function setUp(): void
    {
        $this->hookableValidator = $this->getContainer()->get(HookableValidator::class);
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $this->roleId = Uuid::randomHex();
    }

    public function testValidateDoesNotThrowIfNoWebhooksExist(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../App/Manifest/_fixtures/minimal/manifest.xml');
        $this->hookableValidator->validate($manifest, Context::createDefaultContext());
    }

    public function testValidateDoesNotThrowIfWebhooksAreValid(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../App/Manifest/_fixtures/test/manifest.xml');

        $this->createAppWithAclRole();

        $this->permissionPersister->updatePrivileges($manifest->getPermissions(), $this->roleId);
        $this->hookableValidator->validate($manifest, Context::createDefaultContext());
    }

    public function testValidateThrowsIfWebhooksIncludeNotHookableWebhooks(): void
    {
        $this->createAppWithAclRole();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/invalidWebhooks/includeNotHookableWebhooks/manifest.xml');
        $this->permissionPersister->updatePrivileges($manifest->getPermissions(), $this->roleId);

        static::expectException(HookableValidationException::class);
        static::expectExceptionMessage('SwagAppInvalidWebhooksNotHookable:
The following webhooks are not hookable:
- hook1: tax.written
- hook2: ?§$%
- hook3: ');
        $this->hookableValidator->validate($manifest, Context::createDefaultContext());
    }

    public function testValidateThrowsIfWebhooksMissingPermissions(): void
    {
        $this->createAppWithAclRole();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/invalidWebhooks/missingPermissions/manifest.xml');
        $this->permissionPersister->updatePrivileges($manifest->getPermissions(), $this->roleId);

        static::expectException(HookableValidationException::class);
        static::expectExceptionMessage('SwagAppInvalidWebhooksMissingPermissions:
The following permissions are missing:
- order:read
- product:read');
        $this->hookableValidator->validate($manifest, Context::createDefaultContext());
    }

    private function createAppWithAclRole(): void
    {
        $this->appRepository->create([[
            'id' => Uuid::randomHex(),
            'name' => 'SwagApp',
            'path' => __DIR__ . '/../../Manifest/_fixtures/invalidWebhooks',
            'version' => '0.0.1',
            'label' => 'test',
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $this->roleId,
                'name' => 'SwagApp',
            ],
        ]], Context::createDefaultContext());
    }
}
