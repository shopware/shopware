# Domain Label Catalogue

Suggested labels MUST come from this list — every entry below exists as a real label on `shopware/shopware`. **Use 1–2 labels.** When picking, prefer the primary surface area where the user observes the bug.

> **Maintainer note:** the CI validator `.github/bin/js/validate-triage-output.ts` hardcodes this label set (`VALID_LABELS` / `COMPONENT_LABELS`). When you add or remove a label here, mirror the change there or valid triages will fail validation.

## Primary signal: read the package marker on the affected file

Shopware annotates code ownership directly in the source. **This is the WG-Tech-Architecture-curated source of truth — trust it over any path heuristic and over outdated Confluence text.**

- **PHP** (`src/**/*.php`): class-level attribute `#[Package('<team-key>')]`. PHPStan enforces presence — see `coding-guidelines/core/`.
- **JS / TS** (`src/Administration/**/*.{js,ts}`): JSDoc comment `@sw-package <team-key>`. Present on most module files; grep the file or its `index.{js,ts}`.

**Workflow when you have an affected file:**

```bash
# PHP — single file
rg -N "^#\[Package\('([^']+)'\)\]" <file.php> --replace '$1'

# JS / TS — single file (fall back to the module's index.{js,ts} if no marker on the touched file)
rg -N "@sw-package ([a-zA-Z@:_-]+)" <file.{js,ts}> --replace '$1'

# WHOLE MODULE / DIRECTORY — find the DOMINANT package marker (mixed modules are common;
# do NOT just take the first match — pick the one with the highest count).
rg "@sw-package " <dir> --no-filename | sort | uniq -c | sort -rn | head -3
```

Then map the package key → label using the table below.

### Package key → label mapping

| `#[Package(...)]` key | Label | Notes |
|---|---|---|
| `framework` | `domain/framework` | |
| `inventory` | `domain/inventory` | |
| `discovery` | `domain/discovery` | |
| `checkout` | `domain/checkout` | |
| `after-sales` | `domain/crm-after-sales` | Naming mismatch on purpose: package is `after-sales`, label is `crm-after-sales`. |
| `data-services` | `service/data-intelligence` | Currently only `src/Core/System/Consent/`, `src/Core/System/UsageData/`, and a few migrations carry this key. |
| `fundamentals@framework` | `domain/framework` | "Fundamentals" topic currently owned by the framework team. |
| `fundamentals@discovery` | `domain/discovery` | "Fundamentals" topic currently owned by the discovery team (Country, Language). |
| `fundamentals@after-sales` | `domain/crm-after-sales` | "Fundamentals" topic currently owned by the after-sales team (ImportExport, Rule). |
| `framework:fundamentals` | `domain/framework` | Alternate spelling of `fundamentals@framework` — only seen on a handful of Consent files in admin JS. |
| `innovation` | (no label) | Rare key used on a few admin store / popover / teaser modules. Apply the path heuristic instead. |
| `buyers-experience` | (no label) | Rare key found only in admin spec/test files. Apply the path heuristic to the production file under test. |

If you grepped and got nothing, the file is either non-PHP (Twig / JS / SCSS / YAML / MD) or the missing-attribute PHPStan rule is allow-listed for that path. Fall back to the path heuristic below.

## Fallback: path → label heuristic

The Package distribution per top-level directory (counts from `rg "#\[Package\("`):

### `src/Core/Content/` — mixed

| Subdir | Dominant package | Label |
|---|---|---|
| `Breadcrumb/` | inventory | `domain/inventory` |
| `Category/` | discovery | `domain/discovery` |
| `Cms/` | discovery | `domain/discovery` |
| `ContactForm/` | discovery | `domain/discovery` |
| `Cookie/` | framework | `domain/framework` |
| `Flow/` | after-sales | `domain/crm-after-sales` |
| `ImportExport/` | fundamentals@after-sales | `domain/crm-after-sales` |
| `LandingPage/` | discovery | `domain/discovery` |
| `Mail/`, `MailTemplate/` | after-sales | `domain/crm-after-sales` |
| `MeasurementSystem/` | inventory | `domain/inventory` |
| `Media/` | discovery | `domain/discovery` |
| `Newsletter/` | after-sales | `domain/crm-after-sales` |
| `Product/` | inventory | `domain/inventory` |
| `ProductExport/` | inventory (some discovery) | `domain/inventory` |
| `ProductStream/`, `Property/`, `Seo/` | inventory | `domain/inventory` |
| `RevocationRequest/`, `Shared/` | after-sales | `domain/crm-after-sales` |
| `Rule/` | fundamentals@after-sales | `domain/crm-after-sales` |
| `Sitemap/` | discovery | `domain/discovery` |

### `src/Core/System/` — mixed

| Subdir | Dominant package | Label |
|---|---|---|
| `Consent/`, `UsageData/` | data-services | `service/data-intelligence` |
| `Country/` | fundamentals@discovery | `domain/discovery` |
| `Currency/` | fundamentals@framework | `domain/framework` |
| `CustomEntity/`, `CustomField/` | framework | `domain/framework` |
| `DeliveryTime/`, `Locale/`, `Snippet/` | discovery | `domain/discovery` |
| `Integration/` | fundamentals@framework | `domain/framework` |
| `Language/` | fundamentals@discovery | `domain/discovery` |
| `NumberRange/`, `SystemConfig/` | framework | `domain/framework` |
| `SalesChannel/` | discovery (some framework) | `domain/discovery` |
| `Salutation/`, `StateMachine/`, `Tax/`, `TaxProvider/` | checkout | `domain/checkout` |
| `Tag/`, `User/` | fundamentals@framework | `domain/framework` |
| `Unit/` | inventory | `domain/inventory` |

### `src/Core/Checkout/` — `domain/checkout` (with `after-sales` carve-outs)

Everything except files annotated `after-sales` or `fundamentals@after-sales` (≈ Order documents, return management) → `domain/checkout`.

### `src/Core/Framework/` — `domain/framework`

Includes `App/`, `Webhook/`, `Api/`, `Demodata/`, `Sso/`, `Notification/`, `Mcp/`, `Telemetry/`, `Update/`. **Exception:** `src/Core/Framework/Store/` is annotated `checkout` (Extension Store sits with the Checkout team's extensions). Carve-outs aside, default to `domain/framework`.

### `src/Core/Maintenance/`, `src/Core/Installer/`, `src/Core/DevOps/`, `src/Core/Service/`, `src/Core/Profiling/`, `src/Core/Migration/`

`domain/framework`. Two exceptions: `src/Core/Maintenance/SalesChannel/` → `domain/discovery`; individual migrations under `src/Core/Migration/V6_*/` carry per-domain Package keys — check the migration's own attribute.

### `src/Storefront/`, `src/Administration/` — predominantly framework, with feature carve-outs

The technical layer (Twig infra, Bootstrap, SCSS, Webpack, JS plugins, theme compilation, core admin components, services, helpers, build tooling) is `framework`. Feature subdirectories follow the feature's domain — **always grep the `@sw-package` JSDoc tag (JS/TS) or `#[Package(...)]` attribute (PHP) on the touched file before deciding**, the marker is more reliable than this list.

Top feature-module carve-outs (verified against current `@sw-package` markers — Admin only; settings modules omitted, grep them individually):

- `sw-product/`, `sw-product-stream/`, `sw-property/`, `sw-manufacturer/` → `domain/inventory`
- `sw-cms/`, `sw-category/`, `sw-landing-page/`, `sw-media/`, `sw-sales-channel/`, `sw-export-channel-tracking/` → `domain/discovery`
- `sw-order/`, `sw-customer/`, `sw-promotion-v2/`, `sw-bulk-edit/`, `sw-extension/` → `domain/checkout`
- `sw-dashboard/`, `sw-mail-template/`, `sw-flow/`, `sw-newsletter-recipient/`, `sw-review/`, `sw-import-export/`, `sw-first-run-wizard/` → `domain/crm-after-sales`
- `sw-custom-entity/`, `sw-login/`, `sw-inactivity-login/`, `sw-privilege-error/`, `sw-profile/`, `sw-users-permissions/`, `sw-integration/`, `sw-extension-sdk/` → `domain/framework`

Storefront feature carve-outs:

- `src/Storefront/Page/Account/`, `src/Storefront/Controller/CheckoutController.php`, `src/Storefront/Controller/AccountOrderController.php` → `domain/checkout`
- `src/Storefront/Page/Search/` → `domain/inventory`

### `src/Elasticsearch/`

Mostly `framework` (search infrastructure). `src/Elasticsearch/Product/` and a few inventory-specific indexers are `inventory`. Read the attribute when in doubt.

## Required second label for `domain/framework`

When the primary label is `domain/framework`, **always add a `component/*` label as the second** to indicate which layer is affected. Pick exactly one:

| Component label | When |
|---|---|
| `component/core` | Backend / PHP under `src/Core/`, `src/Elasticsearch/Framework/`, `src/Core/Framework/`, migrations, DI configs |
| `component/administration` | Admin UI under `src/Administration/Resources/app/administration/` |
| `component/storefront` | Storefront under `src/Storefront/` (Twig, JS plugins, SCSS, theme build) |

If multiple layers are touched, pick the one where the user observes the bug (usually `storefront` or `administration` over `core`).

For other domain labels (`domain/inventory`, `domain/checkout`, `domain/discovery`, `domain/crm-after-sales`), a second label is optional and only added if a clear second affected area exists.

## Labels with no code-side `#[Package(...)]` equivalent

These labels exist on `shopware/shopware` but have no `#[Package(...)]` counterpart in this repo. Apply them based on issue topic / context, not on file paths.

| Label | Apply when |
|---|---|
| `domain/b2b` | Issue is about B2B Suite (employee management, order approvals, quote management, quick orders, shopping lists, organization units, Digital Sales Rooms, Sales Agent). The code lives in `src/Commercial/B2B/` (a separate package not always present in this repo). Trigger on keywords: B2B, employee, quote, approval, organisation/organization unit, shopping list, quick order. |
| `domain/dx-tools` | Issue is about developer tooling: `shopware-cli` (separate repo), root-level `.github/` scripts, CI workflows, AI triage / AI tooling. Touched paths: `.github/`, `bin/`, dev composer/npm scripts. |
| `domain/quality-ops` | Issue is about the acceptance test suite (Playwright), `tests/acceptance/`, or QA tooling/processes. |
| `domain/service-enablement` | Issue is about the bridge to external services (cross-org cross-cutting work). No clean code mapping inside this repo — Webhook/App-System/Integration are all `domain/framework` here. Apply rarely, only when the issue is explicitly about service integration plumbing that's not framework-level. |
| `domain/ux` | Issue is about the design system / Meteor Component Library / shared admin look-and-feel. The Meteor library lives in a separate repo. |
| `domain/customer-support` | Pure support tickets where no engineering work is expected. Use sparingly and usually as the **only** domain label — issues that need code changes get a real `domain/*` instead. |
| `domain/product-ops` | Product Operations / cross-cutting process work. Rare on engineering issues. |
| `service/business-capabilities` | Issue is about Service Purchase + Transaction Gateway, SwagRecommendation, SwagAIImageEditor. Code lives outside this repo. |
| `service/data-&-ai-enablement` | Cross-cutting data & AI enablement work. |
| `service/shopping-experience` | Issue is about AI Proxy, Insider Previews, 3D Preview Generator, Copilot, CAD→GLB, AI Copilot commercial features (image keyword assistant, product description/properties generator, review summary/translator, search by context/image, text-to-image), or Spatial Commerce (3D + AR, DIVE viewer, 3D scene editor). Code lives outside this repo. |
| `service/databus-nexus` | Issue is about the Nexus Databus pipeline. Code lives outside this repo. |

## Disambiguation cheatsheet (verified against current code markers)

- **Currencies** → backend `src/Core/System/Currency/` is `fundamentals@framework` → `domain/framework`; admin `sw-settings-currency` is also `fundamentals@framework` → `domain/framework`. Consistent.
- **Tags** → backend `src/Core/System/Tag/` is `fundamentals@framework` → `domain/framework`. BUT admin `sw-settings-tag` is `inventory` → `domain/inventory`. **Read the marker on the touched file.**
- **NumberRange** → backend is `framework`; admin `sw-settings-number-range` is `inventory`. Same split as Tags.
- **UsageData / Consent** → backend `src/Core/System/UsageData/` and `Consent/` are `data-services` → `service/data-intelligence`. BUT admin `sw-settings-usage-data` is `framework` → `domain/framework`.
- **Languages, Countries** → backend `src/Core/System/Language/` and `Country/` are `fundamentals@discovery` → `domain/discovery`. Admin `sw-settings-language` and `sw-settings-country` likewise discovery. Consistent.
- **Customer Groups** → both backend and admin (`sw-settings-customer-group`) are `discovery` → `domain/discovery`.
- **Theme** → compilation/config/build (`src/Storefront/Theme/`) is `framework`. Theme **management UI** in Admin follows the marker on the specific module.
- **Rule Builder authoring** (`src/Core/Content/Rule/`, `sw-settings-rule`) → `fundamentals@after-sales` → `domain/crm-after-sales`. Rules **consumed** inside Cart/Checkout still get `domain/checkout`.
- **Migration Assistant** (SwagMigrationAssistant — separate plugin, not in `src/`) → `domain/crm-after-sales`.
- **Import/Export, Demo Data, First Run Wizard, Newsletter, Mail Templates, Documents, Flow Builder, Reviews** → all `after-sales` family → `domain/crm-after-sales`.
- **App System & Webhooks** (`src/Core/Framework/App/`, `src/Core/Framework/Webhook/`, `src/Core/System/Integration/`) → `framework` → `domain/framework` (not `service-enablement`).
- **Extension Store** (`src/Core/Framework/Store/`, admin `sw-extension`, `sw-settings-store`) → `checkout` → `domain/checkout`.
- **Search settings** (`sw-settings-search`, `src/Elasticsearch/Product/`) → `inventory` → `domain/inventory`. Generic ES infrastructure (`src/Elasticsearch/Framework/`) → `domain/framework`.

**Conflict resolution rules:**

1. If the touched file carries a `#[Package(...)]` or `@sw-package` marker → that wins, full stop.
2. If multiple touched files carry different markers → label both (max 2).
3. If no file marker is available (Twig, SCSS, YAML, MD, frontend without `@sw-package`) → use the path table above.
4. If still ambiguous → pick the **deeper layer** (DAL / framework over UI).
