# MCP Extensibility

Shopware plugins and apps can extend the MCP server with custom tools, prompts, and resources. The MCP server automatically discovers capabilities from plugins installed in `custom/plugins` and `custom/static-plugins`.

## Naming convention

All capability names must only contain `a-zA-Z0-9_-` (no dots) and should use a hyphen-separated prefix:
- **Core**: `shopware-{name}` (e.g., `shopware-entity-search`)
- **Plugin**: `{plugin-name}-{tool-name}` (e.g., `swag-admin-users-admin-users`)
- **App**: `{app-name}-{tool-name}` (e.g., `my-erp-sync-orders`)

This convention applies uniformly to tools, prompts, and resources. The restriction exists because the MCP SDK enforces `a-zA-Z0-9_-` for resource names, and we use a consistent pattern across all types.

## How discovery works

Two mechanisms work together to make plugin tools available:

1. **Attribute discovery** -- The MCP SDK scans directories configured in `mcp.yaml` (`custom/plugins`, `custom/static-plugins`) for PHP classes annotated with `#[McpTool]`, `#[McpPrompt]`, or `#[McpResource]` attributes. The attribute **must be placed on the class**, not on the `__invoke()` method.

2. **DI tag mapping** -- The `McpToolCompilerPass` converts Shopware-specific tags (`shopware.mcp.tool`, `shopware.mcp.prompt`, `shopware.mcp.resource`) to the MCP SDK tags (`mcp.tool`, `mcp.prompt`, `mcp.resource`) so the services are properly wired into the DI container.

Both are needed: the attribute tells the MCP SDK _what_ the tool does (name, description, parameter schema), and the DI tag ensures the service is instantiated with its dependencies.

## Creating a plugin tool

### Plugin structure

```
custom/plugins/SwagMcpAdminUsers/
├── composer.json
└── src/
    ├── SwagMcpAdminUsers.php                # Plugin class (extends Plugin)
    ├── Mcp/
    │   └── AdminUsersTool.php               # MCP tool class
    └── Resources/
        └── config/
            └── services.xml                 # Service definition
```

### Step 1: Create the tool class

Place the `#[McpTool]` attribute on the **class**, not on `__invoke()`. The tool name must include a namespace prefix.

```php
<?php declare(strict_types=1);

namespace Swag\McpAdminUsers\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

#[McpTool(name: 'swag-admin-users-admin-users', description: 'List all admin users of the Shopware instance.')]
class AdminUsersTool
{
    public function __construct(
        private readonly EntityRepository $userRepository,
    ) {
    }

    public function __invoke(): string
    {
        $criteria = new Criteria();
        $criteria->addAssociation('aclRoles');

        $users = $this->userRepository->search($criteria, Context::createDefaultContext());

        $result = [];
        foreach ($users->getElements() as $user) {
            $roles = [];
            foreach ($user->getAclRoles()?->getElements() ?? [] as $role) {
                $roles[] = $role->getName();
            }

            $result[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'active' => $user->getActive(),
                'admin' => $user->isAdmin(),
                'roles' => $roles,
            ];
        }

        return json_encode([
            'total' => $users->getTotal(),
            'users' => $result,
        ], \JSON_THROW_ON_ERROR);
    }
}
```

**Key rules:**

- The `#[McpTool]` attribute goes on the **class**, not on `__invoke()`. The SDK's `Discoverer` only checks class-level attributes for invokable classes.
- Names must only contain `a-zA-Z0-9_-` (no dots) and should use a prefix (e.g., `swag-admin-users-admin-users`)
- Tools must return a `string`. The MCP SDK wraps the return value into the protocol response automatically.
- Use `json_encode()` with `JSON_THROW_ON_ERROR` so failures surface clearly.
- Parameter types on `__invoke()` are mapped to the JSON schema. Supported types: `string`, `int`, `float`, `bool`. Default values make parameters optional.

### Step 2: Register the service

In `src/Resources/config/services.xml`, tag the service with `shopware.mcp.tool`:

```xml
<?xml version="1.0" ?>
<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\McpAdminUsers\Mcp\AdminUsersTool">
            <argument type="service" id="user.repository"/>
            <tag name="shopware.mcp.tool"/>
        </service>
    </services>
</container>
```

Plugin tools do **not** need the `shopware.feature` flag -- that is only for core services. The MCP server feature flag gates the server endpoint itself; once the server is enabled, all registered tools are available.

### Step 3: Exposing console commands

Plugins can expose their console commands to the `shopware-console-command` tool by tagging them with `shopware.mcp.allowed_command`:

```xml
<service id="MyPlugin\Command\MyCommand">
    <tag name="console.command"/>
    <tag name="shopware.mcp.allowed_command"/>
</service>
```

### Step 4: Install and activate

```bash
bin/console plugin:refresh
bin/console plugin:install --activate SwagMcpAdminUsers
bin/console cache:clear
```

After activation, the tool appears in the MCP server immediately. Verify with:

```bash
bin/console debug:mcp
```

### Available tags

| Shopware tag | MCP SDK tag | Purpose |
|---|---|---|
| `shopware.mcp.tool` | `mcp.tool` | Register a tool |
| `shopware.mcp.prompt` | `mcp.prompt` | Register a prompt |
| `shopware.mcp.resource` | `mcp.resource` | Register a resource |
| `shopware.mcp.allowed_command` | -- | Expose a console command to `shopware-console-command` |

## Common pitfalls

### Invalid characters in capability name

Names must only contain `a-zA-Z0-9_-`. Dots are **not allowed**:

```php
// Wrong -- dots not allowed
#[McpTool(name: 'swag-admin-users.admin-users', description: '...')]

// Correct
#[McpTool(name: 'swag-admin-users-admin-users', description: '...')]
```

### Attribute placement

The `#[McpTool]` attribute **must** be on the class, not on `__invoke()`:

```php
// Correct
#[McpTool(name: 'my-plugin-my-tool', description: '...')]
class MyTool
{
    public function __invoke(): string { ... }
}

// Wrong -- tool will not be discovered
class MyTool
{
    #[McpTool(name: 'my-plugin-my-tool', description: '...')]
    public function __invoke(): string { ... }
}
```

### Error handling

Unhandled exceptions in tool execution result in a generic MCP error (`-32603`). Wrap risky operations in try/catch and return structured error JSON instead:

```php
public function __invoke(string $entity): string
{
    try {
        // risky operation
    } catch (\Throwable $e) {
        return json_encode([
            'error' => $e->getMessage(),
        ], \JSON_THROW_ON_ERROR);
    }
}
```

## App tools

Apps declare MCP tools in a `Resources/mcp.xml` file:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<mcp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
     xsi:noNamespaceSchemaLocation="...">
    <mcp-tools>
        <mcp-tool name="sync-orders" url="https://app.example.com/mcp/sync-orders">
            <label>Sync Orders</label>
            <label lang="de-DE">Bestellungen synchronisieren</label>
            <description>Synchronize orders with external ERP</description>
            <input-schema>
                <property name="since" type="string" description="ISO date" required="true"/>
            </input-schema>
        </mcp-tool>
    </mcp-tools>
</mcp>
```

The tool name is automatically prefixed with the app name (e.g., `my-erp-sync-orders`).

### How app tools work

1. `Mcp::createFromXmlFile()` parses the XML during app install/update
2. `McpToolPersister` syncs tool definitions to the `app_mcp_tool` database table
3. `AppMcpToolLoader` loads active app tools at MCP server build time
4. Tool invocations are proxied via HMAC-signed HTTP POST to the app's webhook URL by `AppMcpToolExecutor`

The timeout for app tool calls is configurable via `shopware.mcp.app_tool_timeout` (default: 10 seconds).
