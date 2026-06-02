---
title: Agentic files for sales channels
date: 2026-05-28
area: core, administration, storefront
tags: [ai, agentic, sales-channel, storefront, administration, twig]
---

## Context

Merchants need a way to expose agentic public files such as `llms.txt`, `agents.md`, and metadata below `.well-known/` for each sales channel. These files should be generated from Shopware context, be customizable by merchants, and be extensible by core, plugins, apps, and themes.

The feature should reuse Shopware's established Twig extension model. Core should be able to ship base templates, and extensions should be able to overwrite or extend those templates through normal Twig behavior. We should not introduce an explicit provider interface because the existence of a template in a known location is enough to declare the file.

The merchant-facing configuration belongs to the sales channel detail module. Enablement, preview, public URL, and overrides are sales-channel specific and should not be stored in global system config.

## Decision

We introduce a generic sales-channel file backend. Agentic files are the first file family and are based on template discovery below:

```text
Resources/views/files/agentic/**.twig
```

The feature currently scopes itself to `files/agentic`. The `files` segment acts as a generic public-file template root, while `agentic` is the first supported file family. This leaves room to add other families later, for example `Resources/views/files/seo/robots.txt.twig`.

Terminology is important here: `agentic` and future values such as `seo` are file families. `Framework`, plugin names, app names, and theme names are Twig namespaces. Merchant overrides are keyed by Twig namespace, not by file family.

The relative path below `files/agentic` becomes the public path after removing the `.twig` suffix:

| Template path | Public path |
|---|---|
| `Resources/views/files/agentic/llms.txt.twig` | `/llms.txt` |
| `Resources/views/files/agentic/agents.md.twig` | `/agents.md` |
| `Resources/views/files/agentic/.well-known/ucp.json.twig` | `/.well-known/ucp.json` |

Subfolders are supported explicitly. Dot-prefixed folders such as `.well-known` are valid public path segments, so template discovery must include dot files and dot directories.

Discovery is intentionally split into two steps:

1. Catalogue registered file templates from Shopware's Twig template iterator.
2. Resolve the contributing Twig template chain for each catalogued file through the existing `TemplateFinder`.

This keeps file discovery aligned with the templates registered in the main Twig environment. The catalogue step determines which public files exist, while the chain resolution step follows the same namespace hierarchy Twig uses during rendering.

The catalogue step uses an explicit filtered template-iterator lookup for the `files/<family>` sub path and opts into dot paths for this use case. The default template iterator behavior remains aligned with Symfony and does not expose dot files unless a caller asks for them.

The content type is derived from the public file extension.

Merchant overrides are applied through an internal high-priority Twig loader that participates in the normal Twig loader chain. The loader does not read the database and does not infer state from the current request. Instead, the sales-channel file renderer activates the already loaded override templates for the duration of a single render. This keeps the existing Twig environment, extension set, inheritance behavior, and security configuration intact while allowing Administration previews to render unsaved override content.

The Administration adds an "Agentic files" tab to Storefront and Headless sales channel detail pages. The tab shows a list of discovered files. Selecting a file opens a detail page with rendered preview, enablement, public URL, detected content type, and a list of Twig namespaces that can be overridden individually. The UI stores only merchant overrides and does not copy shipped templates into the database.

The detail page offers two customization levels. Merchants can add simple append-only text through Custom Notes when the template chain exposes `user_provided_content`. Advanced users can open an individual content source and edit the override for that source directly. This keeps the common customization path non-technical while still making full source overrides available when needed.

## Template Examples

Core can ship a base template:

```twig
{# src/Core/Framework/Resources/views/files/agentic/llms.txt.twig #}

{% block agentic_llms_header %}
# {{ context.salesChannel.name }}
{% endblock %}

{% block agentic_llms_summary %}
This shop is powered by Shopware.
{% endblock %}

{% block agentic_llms_guidance %}
Agents may use the public storefront and documented APIs. Respect robots.txt, rate limits, and checkout boundaries.
{% endblock %}

{% block agentic_llms_extensions %}{% endblock %}

{% block user_provided_content %}{% endblock %}
```

Built-in text templates expose empty extension blocks such as `agentic_llms_extensions` and `agentic_agents_extensions` so extensions can append their own content without taking over the core-owned structure blocks. They also expose `user_provided_content`, which the Administration can fill with simple merchant notes through a generated override.

A UCP plugin can extend that template:

```twig
{# custom/plugins/Ucp/Resources/views/files/agentic/llms.txt.twig #}

{% sw_extends '@Framework/files/agentic/llms.txt.twig' %}

{% block agentic_llms_extensions %}
UCP clients may use the product discovery and cart capabilities exposed by this plugin.
{% endblock %}
```

The same plugin can introduce a nested `.well-known` file:

```twig
{# custom/plugins/Ucp/Resources/views/files/agentic/.well-known/ucp.json.twig #}
{
    "schema_version": "1",
    "name_for_model": "{{ context.salesChannel.name|e('js') }}",
    "description_for_model": "Shopware sales channel with UCP capabilities."
}
```

## Routing

Sales channel files are served by a core 404 subscriber on `KernelEvents::EXCEPTION`. The subscriber is intentionally a fallback: it only handles main-request `GET` and `HEAD` requests when normal routing already failed with an unresolved 404.

The public file handling is not an SEO URL and does not participate in SEO URL generation. It must support Storefront and Headless sales channel domains.

Existing explicit routes keep precedence. For example, `/robots.txt`, `/sitemap.xml`, and `/.well-known/change-password` continue to be handled by their dedicated controllers because the subscriber returns early as soon as the request already has a matched route.

The subscriber does not resolve a sales channel context itself. It uses the already-resolved `SalesChannelContext` request attribute and returns early when it is missing. That keeps the implementation independent from Storefront-specific context resolution and lets the active request scope decide whether the file can be served. The subscriber also does not set a hard-coded route scope; it keeps the route scope already resolved for the request.

When the request is eligible, the subscriber validates and normalizes the public path, maps it to the configured `files/agentic` template path, looks up the discovered file descriptor, checks the `sales_channel_file` row for the current sales channel, renders the resolved Twig template stack with merchant overrides, marks the response HTTP-cacheable, and returns it with the derived content type. It returns early for invalid paths, disabled files, and paths that do not resolve to a discovered file descriptor.

Responses are cacheable per sales channel, file family, and public file path. Runtime cache entries are tagged with the matching `sales_channel_file.id` and invalidated through that row-specific tag when the row changes. Discovery and shipped-template changes are code changes and are expected to be deployed with a full cache clear, so no global sales-channel-file cache tag is needed.

## Path Safety

The public path is validated before discovery lookup, database lookup, or Twig rendering. Request input must never be concatenated directly into a Twig template name.

Validation rejects:

- empty paths
- absolute paths
- paths ending with `/`
- empty path segments
- `.` segments
- `..` segments
- backslashes
- NUL bytes
- paths without a file extension
- path segments containing characters outside the allowed segment character set

Dot-prefixed normal segments such as `.well-known` are allowed. The request path is decoded once by the HTTP stack; the resolver must not double-decode encoded input.

The rendering flow is:

```text
request path -> normalize and validate -> discovered descriptor lookup -> load stored overrides -> activate overrides for render -> render descriptor template name
```

This keeps traversal protection in the shared resolver/controller boundary while avoiding template-name construction from raw request input.

## Storage

Sales-channel specific state is stored in a dedicated table:

```text
sales_channel_file
```

The table contains one row per sales channel, file family, and public file:

- `id`
- `sales_channel_id`
- `file_family`
- `file_name`
- `enabled`
- `template_overrides`
- `created_at`
- `updated_at`

`file_name` stores the normalized public file path without a leading slash, for example `llms.txt` or `.well-known/ucp.json`.

`file_family` stores the file family below `Resources/views/files`, for example `agentic`.

`template_overrides` is a JSON object keyed by Twig namespace. The value is the merchant-provided override for that Twig namespace:

```json
{
    "Framework": "{% block agentic_llms_extensions %}...{% endblock %}",
    "Ucp": "{% block agentic_llms_extensions %}...{% endblock %}"
}
```

The reserved `user_provided_content` key stores plain merchant notes. During rendering, it is converted into a generated Twig override for the `user_provided_content` block instead of exposing Twig editing for simple append-only additions.

Resetting an individual source override removes the matching Twig namespace key from `template_overrides`. It never writes the currently shipped template content into the database, so later core or extension template changes become visible again after the reset.

Only overrides are stored in the database. Base templates remain in core, plugin, app, or theme template storage. When a shipped template changes, there is no need to update database rows with a migration.

The Twig override loader is deliberately not a database loader. Persisted overrides are loaded by the sales-channel file application service before rendering, and preview overrides can be supplied directly by the Administration request. This avoids making the global Twig loader depend on sales-channel request state and prevents one sales channel's override from being selected for another sales channel that renders the same Twig template name.

Initial built-in files are opt-in per sales channel. Switching a file from opt-in to opt-out later can be done with a simple migration on a per-file basis.

## Public API and Extension Point

The public API surface should be intentionally small. Only the documented HTTP behavior becomes part of the backward compatibility promise once the feature is released. All PHP services, DAL entities, database tables, generated entity endpoints, template context objects, and implementation details are internal unless they are separately documented as public API.

### Administration HTTP API

The Administration needs an HTTP API to list discovered files and preview unsaved overrides. The HTTP route contract is public, while the PHP controller class remains internal implementation.

- `GET /api/_action/sales-channel-file/{fileFamily}/{salesChannelId}`
- `GET /api/_action/sales-channel-file/{fileFamily}/{salesChannelId}/detail?fileName=<fileName>`
- `POST /api/_action/sales-channel-file/{fileFamily}/{salesChannelId}/preview`

The detail route accepts `fileName` as a query parameter because public file paths can include subfolders such as `.well-known/ucp.json`; this avoids a greedy wildcard route segment for arbitrary file paths. The list response is intentionally lightweight and does not include resolved Twig templates or template source content. The detail response includes resolved template names and the current source template content so the Administration can render the content sources table and prefill the advanced override editor without persisting shipped templates. The exact response shape is documented in the OpenAPI schema.

### Public File HTTP API

- Eligible agentic files are served on sales channel domains by their derived public path, for example `/llms.txt`, `/agents.md`, or `/.well-known/ucp.json`.
- Existing explicit routes keep precedence because the file serving runs only as an unresolved 404 fallback.
- Only `GET` and `HEAD` requests are eligible.
- Disabled files, files without a matching `sales_channel_file` row, invalid paths, and undiscovered files behave like normal 404s.
- The response content type is derived from the public file extension and includes UTF-8 charset.

### Designed Extension Point

Public file template files are the only designed extension point for this feature. Core, plugins, apps, and themes can ship templates below `Resources/views/files/<file-family>/**/*.twig` and use normal Shopware Twig inheritance through Twig namespaces.

The initial extension point is the `agentic` file family with the built-in file paths `files/agentic/llms.txt.twig` and `files/agentic/agents.md.twig`. Subfolders below the file family are supported, including dot-prefixed folders such as `.well-known`.

The exact default text shipped by core is not part of the BC promise and may evolve. Core-owned structure blocks in public file templates may exist to keep templates readable, but extensions should prefer the explicit built-in extension blocks `agentic_llms_extensions` and `agentic_agents_extensions` for additive content. Templates can expose `user_provided_content` when they want the Administration to offer a simple "Custom Notes" field that appends merchant-provided text.

Administration components, services, and Administration Twig templates for this feature are private implementation. They are not designed extension points, are not part of the BC promise, and intentionally do not expose Administration Twig blocks for extension.

## Consequences

Extension authors can add agentic files by shipping Twig templates in a predictable location. They can also extend core templates through standard Twig inheritance instead of registering a provider service.

Merchant customizations are isolated per sales channel and per file. Because the database stores only overrides, deployed template changes remain effective immediately unless a merchant explicitly overrides the affected Twig namespace.

The implementation must ensure template discovery includes dot files and dot directories. App template loading must also allow the `files` template root if apps should ship these templates.

The generic `files/<family>` template structure gives us a path to support other public file families later, such as SEO files.

## Alternatives Considered

### Store overrides in system config

System config would be easy to expose in Administration settings, but it is a poor fit for per-file, sales-channel scoped state and for cache invalidation. A dedicated table gives clearer ownership.

### Store one override row per Twig namespace

A normalized one-row-per-Twig-namespace model would make individual overrides addressable, but rendering and the detail page always need all overrides for a sales channel file. Storing the overrides as one JSON object keeps the read model simple.

### Add a provider interface

A provider interface would make file registration explicit, but it would duplicate information already encoded by the template path. Template discovery keeps the extension point smaller and closer to Shopware's existing Twig model.

### Put the UI in global settings

Global settings would hide the sales-channel specific nature of enablement, public URL, preview, and overrides. The sales channel detail module is the better fit.

## Future Improvements

The model can be extended so merchants can create new public files themselves without core or an extension first shipping a template.

Additional file families can be introduced below `Resources/views/files/*`, for example `files/seo`, while reusing the same validation, routing, storage, and override concepts.
