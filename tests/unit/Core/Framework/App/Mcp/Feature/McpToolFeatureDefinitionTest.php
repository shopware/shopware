<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolFeatureDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolFeatureDefinition::class)]
class McpToolFeatureDefinitionTest extends TestCase
{
    private McpToolFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new McpToolFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('mcp_tool', $this->definition->getType());
        static::assertSame(McpToolConfig::class, $this->definition->getConfigClass());
    }

    public function testExtractReturnsEmptyWhenNoMcpFile(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testExtractReadsToolsFromMcpXml(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertCount(1, $configs);
        $config = $configs[0];
        static::assertSame('sync-orders', $config->name);
        static::assertSame('https://app.example.com/mcp/sync-orders', $config->url);
        static::assertSame(['order:read', 'order:update'], $config->requiredPrivileges);
        static::assertSame(
            ['since' => ['type' => 'string', 'description' => 'ISO date', 'required' => true]],
            $config->inputSchema,
        );
        static::assertSame('Sync Orders', $config->label->forLocale('en-GB'));
        static::assertSame('Bestellungen synchronisieren', $config->label->forLocale('de-DE'));
        static::assertSame('Syncs orders', $config->description->forLocale('en-GB'));
    }

    public function testFromAppFillsMissingDefaultLocaleTranslationFromFallback(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'fr-FR',
        );

        static::assertCount(1, $configs);
        static::assertSame('Sync Orders', $configs[0]->label->forLocale('fr-FR'));
        static::assertSame('Syncs orders', $configs[0]->description->forLocale('fr-FR'));
    }

    public function testFromAppPassesWhenManifestGrantsRequiredPrivileges(): void
    {
        $configs = $this->definition->fromApp(
            $this->manifest(Permissions::fromArray([
                'permissions' => ['order' => ['read', 'update']],
                'additionalPrivileges' => [],
            ])),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertCount(1, $configs);
    }

    public function testFromAppRejectsRequiredPrivilegeMissingFromManifestPermissions(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches('/requires "order:update" but it is not declared in <permissions>/');

        $this->definition->fromApp(
            $this->manifest(Permissions::fromArray([
                'permissions' => ['order' => ['read']],
                'additionalPrivileges' => [],
            ])),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );
    }

    public function testFromAppSkipsPrivilegeValidationWhenManifestHasNoPermissions(): void
    {
        $configs = $this->definition->fromApp(
            $this->manifest(null),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertCount(1, $configs);
    }

    public function testPayloadRoundTripIgnoresStored(): void
    {
        $declared = new McpToolConfig(
            'sync-orders',
            'https://app.example.com/mcp/sync-orders',
            ['order:read'],
            ['since' => ['type' => 'string', 'required' => true]],
            new TranslatedString(['en-GB' => 'Sync Orders']),
            new TranslatedString(['en-GB' => 'Syncs orders']),
        );

        $stored = new McpToolConfig('sync-orders', 'https://stale.example.com', [], null, new TranslatedString(['en-GB' => 'Old']), new TranslatedString([]));

        $payload = $this->definition->toPayload($declared, $stored);
        $hydrated = $this->definition->fromPayload($payload);

        static::assertEquals($declared, $hydrated);
    }

    private function manifest(?Permissions $permissions): Manifest
    {
        $metadata = Metadata::fromArray([
            'label' => ['en-GB' => 'MyApp'],
            'description' => [],
            'name' => 'MyApp',
            'author' => 'shopware AG',
            'copyright' => '(c) shopware AG',
            'license' => 'MIT',
            'version' => '1.0.0',
            'privacyPolicyExtensions' => [],
        ]);

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);
        $manifest->method('getPermissions')->willReturn($permissions);

        return $manifest;
    }
}
