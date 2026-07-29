---
title: MCP server placement and extensibility
date: 2026-03-17
area: framework
tags: [framework, mcp, ai, extensibility, plugin, app]
---

## Context

Shopware now contains an experimental MCP server foundation that exposes a native `/api/_mcp` endpoint and integrates with Shopware authentication, ACL, rate limiting, feature flags, and capability discovery.

The current implementation already supports three different capability sources:

- core-provided MCP capabilities
- in-process extensions from bundles and plugins
- out-of-process app capabilities executed through HMAC-signed webhooks

This raises an architectural question for the current PoC and its future productization:

- Which parts should live in core?
- Which parts should live in a plugin or bundle?
- Which parts should remain external?
- How do we preserve extensibility so plugins and apps can continue to extend the system?

This ADR affects several technical domains:

### Framework and API

The MCP endpoint, transport, authentication bridge, rate limiting, capability discovery, and feature flagging are framework concerns. They should behave consistently for every installation that enables MCP. The current implementation uses `symfony/mcp-bundle` with Streamable HTTP transport at `/api/_mcp`.

### Core business primitives

Generic data access and reference capabilities are platform primitives: reusable building blocks on top of the DAL and Admin API permission system, not opinionated assistant workflows.

### Merchant-facing assistant workflows

Many tools in the current PoC are not low-level primitives. They flatten business workflows into AI-friendly operations: creating products from human-readable parameters, summarizing orders, running reports, managing carts and themes.

These tools are valuable, but they are more opinionated, more likely to change, and more likely to encode business assumptions.

### Storefront and optional bundles

Some capabilities depend on bundle-specific services. `ThemeConfigTool` already lives in `src/Storefront/Mcp/Tool/` for this reason. Other tools like `StorefrontSearchTool`, `CartManageTool`, `CartCheckoutTool`, and `CheckoutMethodsTool` use SalesChannel services from Core (not Storefront imports), so the migration decision is about classification as assistant workflows, not dependency direction.

### Extensibility

The MCP subsystem must remain extendable. Plugins, bundles, and apps need to be able to contribute custom tools, prompts, and resources. The current implementation already supports all three sources through different registration paths:

- plugins use the `shopware.mcp.tool` DI tag, remapped by `McpToolDiscoveryCompilerPass`
- non-core bundles use the `mcp.tool` tag directly
- apps declare capabilities in `Resources/mcp.xml`, loaded at runtime by `AppMcpToolLoader`/`AppMcpPromptLoader`/`AppMcpResourceLoader`

### Security and maintenance

The more tools are moved into core, the more security review, documentation, support, and backward compatibility commitments the platform team takes on. The `allowed_tools` configuration already provides an operational escape hatch to restrict which tools are exposed per installation.

## Decision

We adopt a hybrid model.

### Placement model

- keep the MCP platform foundation in core
- keep most opinionated, workflow-oriented, and fast-changing tools in a plugin or bundle
- use external services only where cross-system orchestration or runtime isolation is the primary concern

In short:

- core = MCP platform primitives
- plugin or bundle = merchant-facing assistant capabilities
- external service = orchestration and isolated integrations

### Core responsibilities

Core owns the MCP platform foundation:

- MCP HTTP endpoint at `/api/_mcp` using Streamable HTTP transport via `symfony/mcp-bundle`
- authentication bridge into the Admin API permission model (`McpContextProvider`)
- ACL enforcement integration
- rate limiting and audit logging (Monolog `mcp` channel)
- feature flagging (`MCP_SERVER`)
- capability discovery via `mcp.yaml` scan_dirs and DI tag scanning
- capability registration through `#[McpTool]`, `#[McpPrompt]`, `#[McpResource]` attributes
- `McpToolDiscoveryCompilerPass`, `McpToolAnalysisCompilerPass`, and `McpServerBuilderCompilerPass` for tag mapping, conflict detection, dependency validation, privilege extraction, and builder registration
- `allowed_tools` operational allowlist to restrict which tools are exposed per installation
- generic reference resources
- the `shopware-context` system prompt
- a small set of generic and stable tool primitives

Core may also own a limited set of low-level, broadly useful tools as long as they behave like platform primitives and not like assistant-specific workflows.

### Plugin or bundle responsibilities

Plugins and bundles should own the merchant-assistant layer:

- opinionated workflow tools
- convenience tools with human-readable parameters
- multi-step business operations
- bundle-specific capabilities
- storefront-specific assistant flows
- analytics and reporting helpers
- content, media, and appearance helpers

These capabilities are useful, but they should not automatically become core platform contracts while the feature is still experimental.

### External responsibilities

External services are appropriate when:

- the main use case spans multiple systems such as ERP, PIM, CRM, or BI
- runtime isolation is more important than deep in-process integration
- the workflow is long-running or operationally heavy
- the capability should scale or deploy independently from Shopware

## Tool placement guidance for the current PoC

### Keep in core

The following capabilities are good candidates for core because they are generic primitives or reference capabilities:

- `shopware-entity-schema`
- `shopware-entity-search`
- `shopware-entity-read`
- `shopware-entity-aggregate`
- `shopware-context` prompt
- static resources: entities, sales channels, currencies, languages, state machines, business events, flow actions

These are low-level building blocks that are broad, composable, and align with a platform role. All of them currently live in `src/Core/Framework/Mcp/` and should stay there.

### Keep in core, but evaluate carefully

These capabilities are still primitive in nature, but they are write-capable and therefore have higher support and security impact:

- `shopware-entity-upsert`
- `shopware-entity-delete`
- `shopware-order-state`
- `shopware-system-config-read`
- `shopware-system-config-write`

If Shopware wants a conservative core MCP surface, some of these can start in a plugin and be promoted later.

### Move to a plugin or bundle

The following capabilities are the clearest plugin or bundle candidates because they are opinionated, workflow-oriented, and likely to evolve through feedback:

- `shopware-order-summary` (currently in Core)
- `shopware-customer-lookup` (currently in Core)
- `shopware-product-create` (currently in Core)
- `shopware-revenue-report` (currently in Core)
- `shopware-bestseller-report` (currently in Core)
- `shopware-storefront-search` (currently in Core)
- `shopware-cart-manage` (currently in Core)
- `shopware-cart-checkout` (currently in Core)
- `shopware-checkout-methods` (currently in Core)
- `shopware-theme-config` (already in Storefront bundle)
- `shopware-media-upload` (currently in Core)

`shopware-theme-config` is already correctly placed in `src/Storefront/Mcp/Tool/` because it depends on Storefront-specific services. The remaining tools all live in `src/Core/Framework/Mcp/Tool/` today and need to be moved if this decision is adopted.

Note that several tools with "storefront" or "cart" in their name (`shopware-storefront-search`, `shopware-cart-manage`, `shopware-cart-checkout`, `shopware-checkout-methods`) use Core SalesChannel services, not Storefront bundle services. The move is about classification as assistant workflows, not about fixing dependency direction violations.

## Extensibility

Extensibility is a hard requirement. The core MCP foundation must act as a capability host, not a closed feature set.

### Plugin and bundle extensibility

Plugins and bundles register custom tools, prompts, and resources through DI and use normal Shopware services. This is the preferred model for domain-specific capabilities, project-specific workflows, vertical features, and capabilities depending on optional bundles.

The current implementation already supports this:

- plugins tag services with `shopware.mcp.tool` (remapped by `McpToolDiscoveryCompilerPass`)
- non-core bundles tag services with `mcp.tool` directly
- `mcp.yaml` `scan_dirs` includes `custom/plugins` and `custom/static-plugins` for attribute discovery

### App extensibility

Apps declare capabilities in `Resources/mcp.xml` and receive HMAC-signed HTTP calls at runtime via `AppMcpToolExecutor`. This is the preferred model for SaaS integrations, remote business logic, and partner extensions that should not run inside the PHP runtime.

### What core must provide for this to work

- naming rules (`shopware-*` for core, `{plugin}-*` for plugins, `{app}-*` for apps)
- `McpToolDiscoveryCompilerPass` for tag mapping, conflict detection, and allowlist enforcement
- `allowed_tools` for operational restriction per installation
- stable registration and discovery contracts
- developer-facing extension documentation

## Decision matrix

| Question | Core | Plugin or bundle | External service |
|---|---|---|---|
| Needed for every Shopware MCP installation? | Best fit | Maybe | Rarely |
| Deep access to internal services and DAL needed? | Best fit | Best fit | Weak fit |
| Fast iteration expected? | Weak fit | Good fit | Best fit |
| Strong isolation needed? | Weak fit | Medium | Best fit |
| Independent release cadence needed? | No | Yes | Yes |
| Tied to optional bundle or domain? | Weak fit | Best fit | Good fit |
| Cross-system orchestration is the main value? | Weak fit | Medium | Best fit |
| Lowest core maintenance burden? | Worst fit | Better fit | Best fit |
| Lowest latency and fewest moving parts? | Best fit | Best fit | Weak fit |
| Stable official platform contract desired? | Best fit | Medium | Medium |

## Public API and extension model

The platform layer that should be treated as public API:

- the `/api/_mcp` endpoint and `symfony/mcp-bundle` transport
- the registration and discovery mechanism (`mcp.yaml`, DI tags, `McpToolDiscoveryCompilerPass`)
- the capability contracts (`#[McpTool]`, `#[McpPrompt]`, `#[McpResource]` attributes and response conventions)
- the security model (authentication, ACL, `allowed_tools`, rate limiting, app HMAC)
- the extension mechanisms for plugins, bundles, and apps

The built-in assistant tools should not automatically be treated as equally stable platform APIs. Most of them are product features built on top of the platform layer.

## Pseudocode

### Core: MCP platform foundation

```php
// mcp.yaml -- SDK discovery configuration
mcp:
  app: 'Shopware'
  version: '1.0.0'
  client_transports:
    http: true
  http:
    path: /api/_mcp
  discovery:
    scan_dirs:
      - src/Core/Framework/Mcp
      - src/Storefront/Mcp
      - custom/plugins
      - custom/static-plugins

// McpToolDiscoveryCompilerPass -- tag mapping and conflict detection
foreach tagged('shopware.mcp.tool') as $service:
    $service->addTag('mcp.tool')
    if $name in $registeredNames:
        throw DuplicateToolNameException
    if $allowedTools is not empty and $name not in $allowedTools:
        $container->removeDefinition($service)
```

### Plugin or bundle: in-process extension

```php
// Plugin tool class
#[McpTool(name: 'swag-erp-sync-orders', description: '...')]
class SyncOrdersTool {
    use McpToolResponse;
    public function __invoke(string $since): string {
        return $this->success([...]);
    }
}

// Plugin services.xml -- uses shopware.mcp.tool tag (remapped by McpToolDiscoveryCompilerPass)
<service id="Swag\Erp\Mcp\Tool\SyncOrdersTool">
    <tag name="shopware.mcp.tool"/>
    <tag name="shopware.feature" flag="MCP_SERVER"/>
</service>

// Non-core bundle (e.g. Storefront) -- uses mcp.tool tag directly
<service id="Shopware\Storefront\Mcp\Tool\ThemeConfigTool">
    <tag name="mcp.tool"/>
    <tag name="shopware.feature" flag="MCP_SERVER"/>
</service>
```

### App: out-of-process extension

```xml
<!-- Resources/mcp.xml -->
<mcp>
  <tools>
    <tool name="my-erp-sync-orders">
      <description>Sync orders to ERP</description>
      <input-schema>{"type":"object","properties":{...}}</input-schema>
      <url>https://my-erp.example.com/mcp/sync-orders</url>
    </tool>
  </tools>
</mcp>
```

```text
// Runtime: AppMcpToolExecutor
client calls shopware-mcp-endpoint -> capability registry resolves app tool
    -> HMAC-signed HTTP POST to app URL
    -> app returns JSON response
    -> MCP server returns result to client
```

### Client perspective

```text
client connects to /api/_mcp (Streamable HTTP, authenticated)
    -> initialize -> capabilities from core + bundles + plugins + apps
    -> tools/list -> unified registry, all sources merged
    -> tools/call -> routed to in-process handler or app webhook
```

## PoC cleanup checklist

The following tools currently live in `src/Core/Framework/Mcp/Tool/` but should be moved to a plugin or bundle according to this decision. Each tool requires moving the class file, removing its DI registration from `mcp.php`, moving its unit test, and updating `McpCapabilityDiscoveryTest` expectations.

### Tools to remove from core (10 files)

| Tool class | MCP name | Category |
|---|---|---|
| `OrderSummaryTool` | `shopware-order-summary` | Workflow |
| `CustomerLookupTool` | `shopware-customer-lookup` | Workflow |
| `ProductCreateTool` | `shopware-product-create` | Workflow |
| `RevenueReportTool` | `shopware-revenue-report` | Reporting |
| `BestsellerReportTool` | `shopware-bestseller-report` | Reporting |
| `StorefrontSearchTool` | `shopware-storefront-search` | Storefront |
| `CartManageTool` | `shopware-cart-manage` | Storefront |
| `CartCheckoutTool` | `shopware-cart-checkout` | Storefront |
| `CheckoutMethodsTool` | `shopware-checkout-methods` | Storefront |
| `MediaUploadTool` | `shopware-media-upload` | Media |

### What stays in core (9 tools + 1 prompt + 7 resources)

| Tool class | MCP name |
|---|---|
| `EntitySchemaTool` | `shopware-entity-schema` |
| `EntitySearchTool` | `shopware-entity-search` |
| `EntityReadTool` | `shopware-entity-read` |
| `EntityAggregateTool` | `shopware-entity-aggregate` |
| `EntityUpsertTool` | `shopware-entity-upsert` |
| `EntityDeleteTool` | `shopware-entity-delete` |
| `OrderStateTool` | `shopware-order-state` |
| `SystemConfigReadTool` | `shopware-system-config-read` |
| `SystemConfigWriteTool` | `shopware-system-config-write` |

Plus `ShopwareContextPrompt` and all seven resources (entities, sales channels, currencies, languages, state machines, business events, flow actions).

### Already correctly placed

`ThemeConfigTool` (`shopware-theme-config`) already lives in `src/Storefront/Mcp/Tool/` with its DI registration in `src/Storefront/DependencyInjection/mcp.xml`.

### Per-tool migration steps

For each tool being removed from core:

1. move `src/Core/Framework/Mcp/Tool/{ToolClass}.php` to the target plugin or bundle
2. remove its `$services->set(...)` block from `src/Core/Framework/DependencyInjection/mcp.php`
3. register it in the target plugin or bundle's DI config (using `shopware.mcp.tool` tag for plugins, `mcp.tool` for bundles)
4. move the unit test from `tests/unit/Core/Framework/Mcp/Tool/`
5. move the tool name from `expectedTools()` in `McpCapabilityDiscoveryTest` (or remove if the plugin is not loaded in integration tests)
6. remove any helper enums or types only used by that tool (e.g. `CartAction`, `CheckoutMethodType`, `RevenueGroupBy`)

## Consequences

### Positive consequences

- core remains small, stable, and supportable
- Shopware keeps a native MCP platform instead of outsourcing the whole feature
- plugins and bundles can iterate faster than core
- apps remain a valid extension mechanism
- mature tools can be promoted from plugin to core later
- bundle-specific capabilities can stay close to their owning domain

### Negative consequences

- the feature is intentionally split between foundation and capabilities
- some tools will remain debatable, especially generic write tools
- packaging and ownership must be communicated clearly
- migration of tools between plugin and core may happen over time

## Why this decision was made

The current PoC contains two different kinds of capabilities:

- platform primitives
- assistant workflows

Treating both kinds as if they belong in core would overcommit the platform too early. Treating both kinds as if they belong outside core would throw away the value of Shopware-native auth, ACL, runtime integration, and discovery.

The hybrid model preserves the strengths of the existing implementation and avoids prematurely turning the full PoC into a long-term core maintenance contract.
