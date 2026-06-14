<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\App\Validation\HookableValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescriber;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescription;

/**
 * @internal
 */
class HookableEventDescriberValidationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURES_DIR = __DIR__ . '/_fixtures';

    private HookableValidator $hookableValidator;

    private string $roleId;

    protected function setUp(): void
    {
        $this->hookableValidator = new HookableValidator(new HookableEventCollector(
            static::createStub(BusinessEventCollector::class),
            static::createStub(DefinitionInstanceRegistry::class),
            new \ArrayIterator([]),
            new \ArrayIterator([new TestHookableEventDescriber()])
        ));
        $this->roleId = Uuid::randomHex();
    }

    public function testValidateDoesNotThrowIfDescriberWebhookIsValid(): void
    {
        $this->createAppWithAclRole('describerWebhooks');
        $manifest = Manifest::createFromXmlFile(self::FIXTURES_DIR . '/describerWebhooks/manifest.xml');

        $validations = $this->hookableValidator->validate($manifest, Context::createDefaultContext());

        static::assertCount(0, $validations);
    }

    public function testValidateThrowsIfDescriberWebhookMissingPermissions(): void
    {
        $this->createAppWithAclRole('describerWebhooksMissingPermissions');
        $manifest = Manifest::createFromXmlFile(self::FIXTURES_DIR . '/describerWebhooksMissingPermissions/manifest.xml');

        $validations = $this->hookableValidator->validate($manifest, Context::createDefaultContext());

        static::assertCount(1, $validations);
        static::assertInstanceOf(MissingPermissionError::class, $validations->first());
        static::assertSame('The following permissions are missing:
- described:read', $validations->first()->getMessage());
    }

    private function createAppWithAclRole(string $appName): void
    {
        static::getContainer()->get('app.repository')->create([[
            'id' => Uuid::randomHex(),
            'name' => $appName,
            'path' => self::FIXTURES_DIR . '/' . $appName,
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
}

/**
 * @internal
 */
class TestHookableEventDescriber implements HookableEventDescriber
{
    public function describe(): array
    {
        return [];
    }

    public function describeForValidation(Manifest $manifest): array
    {
        return [
            new HookableEventDescription('test.described.event', 'Test described event.', ['described:read']),
        ];
    }
}
