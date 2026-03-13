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

### Step 3: Install and activate

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

## App capabilities

Apps declare MCP capabilities (tools, prompts, and resources) in a `Resources/mcp.xml` file. All three types follow the same lifecycle: declared in XML → persisted to DB on install/update → loaded at MCP server build time → invoked via HMAC-signed webhook.

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

    <mcp-prompts>
        <mcp-prompt name="order-context" url="https://app.example.com/mcp/prompts/order-context">
            <label>Order context</label>
            <description>Provides background context for working with orders in this app</description>
        </mcp-prompt>
    </mcp-prompts>

    <mcp-resources>
        <mcp-resource name="erp-status" url="https://app.example.com/mcp/resources/erp-status">
            <label>ERP status</label>
            <description>Current ERP connection status and last sync timestamp</description>
        </mcp-resource>
    </mcp-resources>

</mcp>
```

All names are automatically prefixed with the app name (e.g., `my-erp-sync-orders`, `my-erp-order-context`, `my-erp-erp-status`).

### How app capabilities work

| Step | Tools | Prompts | Resources |
|---|---|---|---|
| **Parse** | `Mcp::createFromXmlFile()` | same | same |
| **Persist** | `McpToolPersister` → `app_mcp_tool` | `McpPromptPersister` → `app_mcp_prompt` | `McpResourcePersister` → `app_mcp_resource` |
| **Load** | `AppMcpToolLoader` | `AppMcpPromptLoader` | `AppMcpResourceLoader` |
| **Execute** | `AppMcpToolExecutor` (HMAC-signed POST) | same executor | same executor |

The timeout for all app webhook calls is configurable via `shopware.mcp.app_tool_timeout` (default: 10 seconds).

### App webhook protocol

When an AI client invokes a tool, requests a prompt, or reads a resource, Shopware sends a signed HTTP POST to the URL declared in `mcp.xml`. The request body contains the invocation arguments as JSON. The app server must respond with the result in the format the MCP SDK expects for that capability type.

Requests are signed using the app secret (HMAC-SHA256). Apps should verify the signature on every incoming request.

### Locale resolution

Translations for app capability labels and descriptions are resolved against the **system's default locale** at load time (not hardcoded to `en-GB`). The loaders query `Defaults::LANGUAGE_SYSTEM` to find the active locale code. If the locale cannot be determined, they fall back to `en-GB`.

## Keeping responses compact with McpEntityIncludes

If your plugin tool returns DAL entity data via `JsonEntityEncoder`, use the `McpEntityIncludes` trait to automatically strip unrequested associations and keep responses under the 100 KB size limit.

```php
<?php declare(strict_types=1);

namespace MyPlugin\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Mcp\Tool\McpEntityIncludes;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(name: 'my-plugin-product-list', description: 'List products with compact response')]
class ProductListTool
{
    use McpEntityIncludes;
    use McpToolResponse;

    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly JsonEntityEncoder $encoder,
    ) {
    }

    public function __invoke(string $criteria = '{}'): string
    {
        $definition = $this->registry->getByEntityName('product');
        $repository = $this->registry->getRepository('product');

        $criteriaObj = $this->criteriaBuilder->fromArray(
            json_decode($criteria, true, 512, \JSON_THROW_ON_ERROR),
            new Criteria(),
            $definition,
            \Shopware\Core\Framework\Context::createDefaultContext(),
        );

        // Apply smart includes when the caller hasn't specified their own
        if ($criteriaObj->getIncludes() === null) {
            $criteriaObj->setIncludes($this->buildDefaultIncludes($definition, $criteriaObj));
        }

        $result = $repository->search($criteriaObj, \Shopware\Core\Framework\Context::createDefaultContext());
        $encoded = $this->encoder->encode($criteriaObj, $definition, $result->getEntities(), '/api');

        return $this->success($encoded, ['total' => $result->getTotal()]);
    }
}
```

The trait walks the entity definition and the criteria's requested associations to build an `includes` map that:
- Includes all scalar fields (id, name, price, stock, etc.) of every entity involved
- Includes only association fields that are explicitly requested in the criteria
- Strips auto-loaded noise like thumbnails, extensions, and translated duplicates

This is optional but recommended for any tool returning DAL entity data via `JsonEntityEncoder`.
