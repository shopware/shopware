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
use Shopware\Core\Service\Event\CommercialLicenseProvidedEvent;

/**
 * @internal
 */
class HookableValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private HookableValidator $hookableValidator;

    private string $roleId;

    protected function setUp(): void
    {
        $this->hookableValidator = static::getContainer()->get(HookableValidator::class);
        $this->roleId = Uuid::randomHex();
    }

    public function testValidateDoesNotThrowIfNoWebhooksExist(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../App/Manifest/_fixtures/minimal/manifest.xml');
        $validations = $this->hookableValidator->validate($manifest, Context::createDefaultContext());
        static::assertCount(0, $validations);
    }

    public function testValidateDoesNotThrowIfWebhooksAreValid(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../App/Manifest/_fixtures/test/manifest.xml');
        $this->createAppWithAclRole('test');

        $validations = $this->hookableValidator->validate($manifest, Context::createDefaultContext());

        static::assertCount(0, $validations);
    }

    public function testValidateThrowsIfWebhooksIncludeNotHookableWebhooks(): void
    {
        $this->createAppWithAclRole('notHookableWebhooks');
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/notHookableWebhooks/manifest.xml');

        $validations = $this->hookableValidator->validate($manifest, Context::createDefaultContext());

        static::assertCount(1, $validations);
        static::assertInstanceOf(NotHookableError::class, $validations->first());
        static::assertSame('The following webhooks are not hookable:
- hook1: tax.written
- hook2: test.event', $validations->first()->getMessage());
    }

    public function testValidateThrowsIfWebhooksMissingPermissions(): void
    {
        $this->createAppWithAclRole('missingPermissions');
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/missingPermissions/manifest.xml');

        $validations = $this->hookableValidator->validate($manifest, Context::createDefaultContext());

        static::assertCount(1, $validations);
        static::assertInstanceOf(MissingPermissionError::class, $validations->first());
        static::assertSame('The following permissions are missing:
- order:read
- product:read', $validations->first()->getMessage());
    }

    public function testCommercialLicenseWebhookIsNotHookableForRegularApps(): void
    {
        $manifest = Manifest::createFromXml($this->createManifestWithWebhook('app-with-commercial-license', CommercialLicenseProvidedEvent::NAME));

        $validations = $this->hookableValidator->validate($manifest, Context::createDefaultContext());

        static::assertCount(1, $validations);
        static::assertInstanceOf(NotHookableError::class, $validations->first());
        static::assertSame(
            'The following webhooks are not hookable:
- commercial-license: ' . CommercialLicenseProvidedEvent::NAME,
            $validations->first()->getMessage()
        );
    }

    public function testCommercialLicenseWebhookIsHookableForServices(): void
    {
        $manifest = Manifest::createFromXml($this->createManifestWithWebhook('service-with-commercial-license', CommercialLicenseProvidedEvent::NAME));
        $manifest->getMetadata()->setSelfManaged(true);

        $validations = $this->hookableValidator->validate($manifest, Context::createDefaultContext());

        static::assertCount(0, $validations);
    }

    private function createAppWithAclRole(string $appName): void
    {
        static::getContainer()->get('app.repository')->create([[
            'id' => Uuid::randomHex(),
            'name' => $appName,
            'path' => __DIR__ . '/../../App/Manifest/_fixtures/' . $appName,
            'version' => '0.0.1',
            'label' => 'test',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $this->roleId,
                'name' => $appName,
            ],
        ]], Context::createDefaultContext());
    }

    private function createManifestWithWebhook(string $name, string $eventName): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
    <meta>
        <name>{$name}</name>
        <label>Webhook test</label>
        <description>Webhook test</description>
        <author>shopware AG</author>
        <copyright>(c) by shopware AG</copyright>
        <version>1.0.0</version>
        <license>MIT</license>
    </meta>
    <setup>
        <registrationUrl>https://app.example.com/registration</registrationUrl>
        <secret>s3cr3t</secret>
    </setup>
    <webhooks>
        <webhook name="commercial-license" url="https://app.example.com/webhook" event="{$eventName}"/>
    </webhooks>
</manifest>
XML;
    }
}
