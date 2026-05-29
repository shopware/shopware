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
```

Built-in text templates expose empty extension blocks such as `agentic_llms_extensions` and `agentic_agents_extensions` so extensions can append their own content without taking over the core-owned structure blocks.

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

Only overrides are stored in the database. Base templates remain in core, plugin, app, or theme template storage. When a shipped template changes, there is no need to update database rows with a migration.

The Twig override loader is deliberately not a database loader. Persisted overrides are loaded by the sales-channel file application service before rendering, and preview overrides can be supplied directly by the Administration request. This avoids making the global Twig loader depend on sales-channel request state and prevents one sales channel's override from being selected for another sales channel that renders the same Twig template name.

Initial built-in files are opt-in per sales channel. Switching a file from opt-in to opt-out later can be done with a simple migration on a per-file basis.

## Public Surface and Extension Points

The public surface should be intentionally small. The following contracts become part of the backward compatibility promise once the feature is released:

### Template Contract

- The template registration convention `Resources/views/files/<file-family>/**/*.twig`.
- The initial `agentic` file family and the built-in file paths `files/agentic/llms.txt.twig` and `files/agentic/agents.md.twig`.
- Public path derivation by stripping `files/agentic/` and the `.twig` suffix for the served agentic file.
- Subfolder support below the file family, including dot-prefixed folders such as `.well-known`.
- Normal Shopware Twig inheritance for those templates, including extension by core, plugins, apps, and themes through Twig namespaces.
- The rendering context variables `context`, `salesChannel`, and `salesChannelFile`. The `salesChannelFile` value exposes the file family, file name, template path, content type, base template name, and resolved Twig template map for read-only template use.
- The explicit built-in extension blocks `agentic_llms_extensions` and `agentic_agents_extensions`.

The exact default text shipped by core is not part of the BC promise and may evolve. Core-owned structure blocks may exist to keep templates readable, but extensions should prefer the explicit extension blocks for additive content.

### Storage and Admin API Contract

- The DAL entity `sales_channel_file`.
- The `salesChannelFiles` association on `sales_channel`.
- The fields `id`, `salesChannelId`, `fileFamily`, `fileName`, `enabled`, and `templateOverrides`.
- The uniqueness semantics of one row per sales channel, file family, and file name.
- The `templateOverrides` JSON shape as an object keyed by Twig namespace with string Twig template content as values.
- The generated Administration API entity endpoints for reading and writing `sales_channel_file` rows.

The database stores merchant configuration only. Shipped templates remain in code or app template storage and are intentionally not part of persisted state.

### Action API Contract

The Administration needs an API to list discovered files and preview unsaved overrides. The HTTP route contract is public, while the PHP controller class remains internal implementation.

`GET /api/_action/sales-channel-file/{fileFamily}/{salesChannelId}` returns:

```json
{
    "data": [
        {
            "fileFamily": "agentic",
            "fileName": "llms.txt",
            "templatePath": "files/agentic/llms.txt.twig",
            "contentType": "text/plain; charset=utf-8",
            "templates": [
                {
                    "twigNamespace": "Framework",
                    "templateName": "@Framework/files/agentic/llms.txt.twig"
                }
            ],
            "configuration": {
                "id": "018f...",
                "enabled": true,
                "templateOverrides": {
                    "Framework": "{% block agentic_llms_extensions %}...{% endblock %}"
                }
            }
        }
    ]
}
```

`POST /api/_action/sales-channel-file/{fileFamily}/{salesChannelId}/preview` accepts:

```json
{
    "fileName": "llms.txt",
    "templateOverrides": {
        "Framework": "{% block agentic_llms_extensions %}...{% endblock %}"
    }
}
```

and returns:

```json
{
    "fileName": "llms.txt",
    "contentType": "text/plain; charset=utf-8",
    "content": "rendered preview content"
}
```

### Public HTTP Contract

- Eligible agentic files are served on sales channel domains by their derived public path, for example `/llms.txt`, `/agents.md`, or `/.well-known/ucp.json`.
- Existing explicit routes keep precedence because the file serving runs only as an unresolved 404 fallback.
- Only `GET` and `HEAD` requests are eligible.
- Disabled files, files without a matching `sales_channel_file` row, invalid paths, and undiscovered files behave like normal 404s.
- The response content type is derived from the public file extension and includes UTF-8 charset.

### Internal Implementation Surface

The PHP services and implementation classes under `Shopware\Core\System\SalesChannel\File`, including discovery, loaders, rendering, request path resolving, the 404 subscriber, and the API controller class, are internal. They are not service decoration or direct injection extension points. Extensions should use the documented template, DAL/Admin API, action API, and Twig block contracts instead.

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
