<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpToolPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpTool;
use Shopware\Core\Framework\App\Mcp\Xml\McpTools;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolPersister::class)]
class McpToolPersisterTest extends TestCase
{
    /**
     * @var EntityRepository<AppMcpToolCollection>&Stub
     */
    private EntityRepository&Stub $mcpToolRepository;

    private McpToolPersister $persister;

    protected function setUp(): void
    {
        $this->mcpToolRepository = static::createStub(EntityRepository::class);
        $this->persister = new McpToolPersister($this->mcpToolRepository);
    }

    public function testValidateRequiredPrivilegesPassesWhenPrivilegesMatchManifest(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'my-tool',
            'url' => '/api/script/my-tool',
            'label' => ['en-GB' => 'My Tool'],
            'description' => [],
            'requiredPrivileges' => ['product:read'],
        ]);
        $mcp = $this->createMcpWithTools(McpTools::fromArray(['tools' => [$tool]]));

        $manifest = $this->createManifest('MyApp', Permissions::fromArray([
            'permissions' => ['product' => ['read']],
            'additionalPrivileges' => [],
        ]));

        $this->expectNotToPerformAssertions();
        $this->persister->validateRequiredPrivileges($manifest, $mcp);
    }

    public function testValidateRequiredPrivilegesThrowsWhenPrivilegeNotInManifest(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'my-tool',
            'url' => '/api/script/my-tool',
            'label' => ['en-GB' => 'My Tool'],
            'description' => [],
            'requiredPrivileges' => ['order:read'],
        ]);
        $mcp = $this->createMcpWithTools(McpTools::fromArray(['tools' => [$tool]]));

        $manifest = $this->createManifest('MyApp', Permissions::fromArray([
            'permissions' => ['product' => ['read']],
            'additionalPrivileges' => [],
        ]));

        $this->expectException(AppException::class);
        $this->persister->validateRequiredPrivileges($manifest, $mcp);
    }

    public function testValidateRequiredPrivilegesThrowsWhenTheManifestHasNoPermissions(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'my-tool',
            'url' => '/api/script/my-tool',
            'label' => ['en-GB' => 'My Tool'],
            'description' => [],
            'requiredPrivileges' => ['order:read'],
        ]);
        $mcp = $this->createMcpWithTools(McpTools::fromArray(['tools' => [$tool]]));

        $manifest = $this->createManifest('MyApp', null);

        // Without <permissions> the app has no privileges, so the tool could never read orders.
        $this->expectExceptionObject(AppException::invalidConfiguration(
            'MyApp',
            new MissingPermissionError(['Tool "my-tool" requires "order:read" but it is not declared in <permissions>']),
        ));
        $this->persister->validateRequiredPrivileges($manifest, $mcp);
    }

    public function testValidateRequiredPrivilegesAcceptsToolsWithoutRequirementsWhenTheManifestHasNoPermissions(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'my-tool',
            'url' => '/api/script/my-tool',
            'label' => ['en-GB' => 'My Tool'],
            'description' => [],
        ]);
        $mcp = $this->createMcpWithTools(McpTools::fromArray(['tools' => [$tool]]));

        $this->expectNotToPerformAssertions();
        $this->persister->validateRequiredPrivileges($this->createManifest('MyApp', null), $mcp);
    }

    public function testValidateRequiredPrivilegesSkipsToolsWithoutRequirements(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'my-tool',
            'url' => '/api/script/my-tool',
            'label' => ['en-GB' => 'My Tool'],
            'description' => [],
        ]);
        $mcp = $this->createMcpWithTools(McpTools::fromArray(['tools' => [$tool]]));

        $manifest = $this->createManifest('MyApp', Permissions::fromArray([
            'permissions' => ['product' => ['read']],
            'additionalPrivileges' => [],
        ]));

        $this->expectNotToPerformAssertions();
        $this->persister->validateRequiredPrivileges($manifest, $mcp);
    }

    public function testValidateRequiredPrivilegesSkipsWhenNullMcp(): void
    {
        $manifest = $this->createManifest('MyApp', Permissions::fromArray([
            'permissions' => ['product' => ['read']],
            'additionalPrivileges' => [],
        ]));

        $this->expectNotToPerformAssertions();
        $this->persister->validateRequiredPrivileges($manifest, null);
    }

    private function createMcpWithTools(McpTools $mcpTools): Mcp
    {
        $mcp = static::createStub(Mcp::class);
        $mcp->method('getTools')->willReturn($mcpTools);

        return $mcp;
    }

    private function createManifest(string $appName, ?Permissions $permissions): Manifest
    {
        $metadata = Metadata::fromArray([
            'label' => ['en-GB' => $appName],
            'description' => [],
            'name' => $appName,
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
