<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\App\Validation\Error\NotHookableError;
use Shopware\Core\Framework\App\Validation\HookableValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
class HookableValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private HookableValidator $hookableValidator;

    private string $roleId;

    public function setUp(): void
    {
        $this->hookableValidator = $this->getContainer()->get(HookableValidator::class);
        $this->roleId = Uuid::randomHex();
    }

    public function testValidateDoesNotThrowIfNoWebhooksExist(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../App/Manifest/_fixtures/minimal/manifest.xml');
        $this->hookableValidator->validate($manifest, Context::createDefaultContext());
    }

    /**
     * Due to the deprecation of NewsletterUpdateEvent (in NewsletterEvents).
     * Only remove DisabledFeatures not the method when removing the event
     *
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testValidateDoesNotThrowIfWebhooksAreValid(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../App/Manifest/_fixtures/test/manifest.xml');
        $this->createAppWithAclRole('test');

        $validations = $this->hookableValidator->validate($manifest, Context::createDefaultContext());

        static::assertCount(0, $validations);
    }

    /**
     * Due to the deprecation of NewsletterUpdateEvent (in NewsletterEvents).
     * Only remove DisabledFeatures not the method when removing the event
     *
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testValidateThrowsIfWebhooksIncludeNotHookableWebhooks(): void
    {
        $this->createAppWithAclRole('notHookableWebhooks');
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/notHookableWebhooks/manifest.xml');

        $validations = $this->hookableValidator->validate($manifest, Context::createDefaultContext());

        static::assertCount(1, $validations);
        static::assertInstanceOf(NotHookableError::class, $validations->first());
        static::assertEquals('The following webhooks are not hookable:
- hook1: tax.written
- hook2: test.event', $validations->first()->getMessage());
    }

    /**
     * Due to the deprecation of NewsletterUpdateEvent (in NewsletterEvents).
     * Only remove DisabledFeatures not the method when removing the event
     *
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testValidateThrowsIfWebhooksMissingPermissions(): void
    {
        $this->createAppWithAclRole('missingPermissions');
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/missingPermissions/manifest.xml');

        $validations = $this->hookableValidator->validate($manifest, Context::createDefaultContext());

        static::assertCount(1, $validations);
        static::assertInstanceOf(MissingPermissionError::class, $validations->first());
        static::assertEquals('The following permissions are missing:
- order:read
- product:read', $validations->first()->getMessage());
    }

    private function createAppWithAclRole(string $appName): void
    {
        $this->getContainer()->get('app.repository')->create([[
            'id' => Uuid::randomHex(),
            'name' => $appName,
            'path' => __DIR__ . '/../../App/Manifest/_fixtures/' . $appName,
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
                'name' => $appName,
            ],
        ]], Context::createDefaultContext());
    }
}
