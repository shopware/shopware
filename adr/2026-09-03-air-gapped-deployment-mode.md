---
title: Air-gapped deployment mode
date: 2026-09-03
area: framework
tags: [deployment, config, store, usage-data, translations, services]
---

## Context

Some Shopware installations run without outbound internet: government, defense, regulated industry, and other isolated networks. Core still performs Shopware-operated HTTP calls on a regular schedule and from the Administration. When those hosts are unreachable the result is not a clean offline shop. It is timeouts, failed scheduled tasks, license-check errors, First Run Wizard steps that cannot complete, and store/update toasts.

Those calls are already gated by several independent knobs:

- `shopware.auto_update.enabled` and `SHOPWARE_DISABLE_UPDATE_CHECK` for the recovery/update client (`releases.shopware.com`, GitHub installer download).
- `shopware.store.frw` for the First Run Wizard store steps.
- `shopware.deployment.runtime_extension_management` for installing or updating extensions at runtime. The Administration already reads this as `settings.disableExtensionManagement`.
- `shopware.translation.use_local_filesystem` and `shopware.translation.scheduled_task.enabled` for Crowdin/GitHub translation fetches.
- `shopware.usage_data.collection_enabled` and `shopware.usage_data.gateway.dispatch_enabled` for `data.shopware.io`.
- `core.services.disabled` (system config, not `shopware.yaml`) for the service registry at `registry.services.shopware.io`.
- Product analytics is gated only by consent plus `PRODUCT_ANALYTICS_GATEWAY_URL` / `shopware.analytics.gateway_url`.
- Store and in-app-purchase traffic (`api.shopware.com`) has no operator kill switch. `StoreClient`, `FirstRunWizardClient`, `TrackingEventClient`, `KeyFetcher`, and `InAppPurchaseUpdater` always talk to the configured `core.store.apiUri`.

An operator who wants a silent air-gapped shop today must find and set all of those, and still cannot stop Store HTTP. That is the gap.

This mode is an environment property, not a Store-only flag. It belongs next to the other deployment knobs (`blue_green`, `cluster_setup`, `runtime_extension_management`).

## Decision

Add one operator-facing toggle:

```yaml
# config/packages/shopware.yaml
shopware:
    deployment:
        air_gapped: false
```

Default is `false`. When `true`, Shopware must not initiate HTTP to Shopware-operated SaaS or content hosts. Local commerce, local plugin lifecycle, and merchant-configured integrations stay available.

### Scope: Shopware-operated egress only

Air-gapped mode disables Shopware's own cloud and content endpoints. It is not a process-wide HTTP firewall.

**In scope (must not leave the host):**

| Client | Host / config | Typical trigger |
| --- | --- | --- |
| `StoreClient`, `StoreClientFactory` | `core.store.apiUri` (`https://api.shopware.com`) | Store login, extension listing, updates, license checks, downloads, ratings |
| `FirstRunWizardClient` | Store API | FRW account / domain / plugin steps |
| `TrackingEventClient` | `/swplatform/tracking/events` | FRW and store tracking |
| `KeyFetcher`, `InAppPurchaseUpdater` | `/inappfeatures/jwks`, purchases | Daily `in-app-purchase.update` task and IAP validation |
| `Update\Services\ApiClient` | `releases.shopware.com`, GitHub web-installer | Update check, recovery-tool download |
| Translation loader / `UpdateTranslationsTask` | `raw.githubusercontent.com/shopware/translations`, `translate.shopware.com` | Daily `translation.update`, language install |
| Usage-data `GatewayClient` | `https://data.shopware.io` | Daily `usage_data.entity_data.collect`, consent reporter |
| Service registry `Client` | `https://registry.services.shopware.io` | Daily `services.install`, Services settings |
| Admin product analytics | `product-analytics-gateway.services.shopware.io` | Browser telemetry after consent |
| Installer GTC fetch | `api.shopware.com/gtc` | Web installer legal texts |

**Out of scope (operator-owned, stay as configured):**

- App webhooks and app backends
- Payment, shipping, and tax providers
- SMTP / mailer
- Object storage, CDN, Fastly
- Elasticsearch / OpenSearch
- Google reCAPTCHA / other captchas
- Media upload-by-URL of merchant URLs
- Composer / Packagist (deploy-time, not runtime Shopware)

A global HTTP deny-list would break payments, apps, and storage. Those remain the operator's responsibility.

`runtime_extension_management` stays a separate concern. Air-gapped mode must not imply it. Isolated shops can still activate, deactivate, and zip-install locally provisioned plugins. They must not browse, buy, or download from the Store.

### One source of truth

Do not scatter twenty `if ($airGapped)` checks that each invent their own meaning.

Introduce an internal, `@internal` service that reads `shopware.deployment.air_gapped` and answers one question: are Shopware-operated outbound calls allowed? Existing per-subsystem flags stay independently usable for partial disable (for example disable updates but keep Store). Air-gapped mode is the conjunction: if the mode is on, those Shopware-operated calls are off regardless of the finer flags.

Enforcement is two-layered:

1. **Policy.** Scheduled tasks that only exist to talk to Shopware SaaS return `false` from `shouldRun()`. The Administration `_info/config` payload exposes `settings.airGapped`. Store / update / services / analytics / usage-data / FRW UIs hide or short-circuit from that flag. Controllers and CLI commands that would otherwise call Store return a dedicated exception immediately.
2. **Safety net.** The Store Guzzle stack (`shopware.store_client`, `shopware.store_download_client`, FRW client) gets middleware that rejects every request when the mode is on. Update, usage-data, translation, and service-registry clients refuse before `request()`. This prevents a missed call site from hanging on DNS or TCP timeout.

Do not implement this as a compiler pass that rewrites other config values. Rewriting `auto_update.enabled` or `usage_data.gateway.dispatch_enabled` makes dumps and tests lie about what the operator wrote. The air-gapped service is consulted in addition to those flags.

### Failure contract

When a Shopware-operated call is attempted in air-gapped mode, fail immediately with a dedicated domain exception (for example `StoreException::airGapped()` / a framework equivalent for non-store clients). Do not wait for cURL error 7. HTTP status should be a client-visible 4xx/503 that the Administration already knows how to surface as a notification, not a 500.

Scheduled tasks and tracking fire-and-forget paths must no-op, not throw into the messenger retry loop.

### Administration

Follow the `disableExtensionManagement` pattern in `InfoController` and `Shopware.Store.get('context').app.config.settings`.

When `airGapped` is true the Administration must:

- Hide Extension Store / marketplace and Store login.
- Skip FRW Shopware-account and domain steps (same as `disableExtensionManagement`, plus any remaining Store calls).
- Hide or disable the in-app update checker.
- Hide usage-data and product-analytics consent prompts, or treat them as revoked / unavailable.
- Hide Shopware Services registry actions.
- Keep local extension listing, config, activate/deactivate, and zip upload.

Empty states should say the installation is air-gapped, not that the Store is temporarily unavailable.

### Config surface

Wire the boolean through the existing deployment config path:

- `Configuration::createDeploymentSection()` — `booleanNode('air_gapped')->defaultFalse()`
- `src/Core/Framework/Resources/config/packages/shopware.yaml` — `deployment.air_gapped: false`
- `config-schema.json` — `definitions.deployment.properties.air_gapped`
- `ConfigurationTest` coverage for the new node

No system-config UI toggle. This is an operator / hosting decision, same as `cluster_setup`. Changing it requires a cache clear, which is acceptable.

### Translations in air-gapped mode

Remote Crowdin/GitHub fetches are Shopware-operated egress, so they stop. Language install must use the existing offline path (`translation:install --offline` / `use_local_filesystem`). The daily `translation.update` task must not run. Document that locales have to be provisioned on disk.

## Considered approaches

### Document the existing flags only

Operators would set `auto_update.enabled`, usage-data dispatch, translation filesystem, services disabled, and `SHOPWARE_DISABLE_UPDATE_CHECK`. This was rejected because Store and in-app-purchase traffic still leave the host, and the checklist is easy to get wrong.

### `shopware.store.enabled: false`

Too narrow. Updates, translations, usage data, services, and analytics are not the Store client. A Store-only flag would recreate today's fragmentation.

### Process-wide HTTP client decorator

A Guzzle / Symfony HttpClient wrapper that denies every non-loopback request would match a literal air gap. It was rejected because it would break merchant-configured payments, apps, mail, and object storage, and it would be almost impossible to test without mocking the world. Isolated networks that also block those destinations already do so at the firewall.

### Compiler-pass override of existing flags

Forcing `auto_update.enabled` and friends to `false` at compile time looks convenient. It was rejected because container parameter dumps would no longer match `shopware.yaml`, and tests that set one fine-grained flag would silently change meaning.

## Consequences

### Positive

- One `shopware.yaml` line is enough for a hosting profile that must not talk to Shopware SaaS.
- Existing fine-grained flags remain valid for shops that only want to turn off updates or usage data.
- Local plugin and app lifecycle stays usable.
- Missed call sites fail closed (immediate exception) instead of hanging.
- The Administration can hide dead Store/SaaS UI instead of showing errors.

### Negative / trade-offs

- In-app purchases and Store-backed license checks cannot refresh. Commercial features that require a live JWKS or license host will not work in this mode. That is intentional.
- Remote translation updates stop. Operators must ship locale files.
- Shopware Services cannot install or update from the registry.
- The web installer GTC fetch still needs an installer-specific follow-up if the installer runs before project `shopware.yaml` is loaded.
- New Shopware-operated HTTP clients must consult the air-gapped service. The safety-net middleware covers the Store stack; other clients need an explicit guard. A unit test or PHPStan rule that new tagged Store/SaaS clients are wired through the guard is recommended in implementation.

### Operational impact

```yaml
shopware:
    deployment:
        air_gapped: true
```

After changing the value, clear the container cache. Provision translation files locally if extra locales are required. Do not expect the Extension Store, in-app updates, usage-data upload, product analytics, or Shopware Services registry to function.

Merchant integrations (payments, apps, SMTP, S3) are unchanged. If the network also blocks those, configure or disable them separately.

### Implementation order (when this ADR is accepted)

1. Config node, schema, `ConfigurationTest`, internal air-gapped service, `RELEASE_INFO` operator note.
2. Store / FRW / tracking / IAP clients and `shouldRun()` on `InAppPurchaseUpdateTask`. Dedicated exception. Admin `_info/config` flag.
3. Update client, usage-data collect/dispatch, translation scheduled task and remote loader, service registry / `InstallServicesTask`.
4. Administration: hide Store, updates, services, analytics/usage-data prompts; FRW skip; Jest coverage.
5. Installer GTC as a follow-up if the installer is in scope for the same release.

Each step needs PHPUnit (and Admin Jest where UI changes). Behaviour change without tests is not done.
