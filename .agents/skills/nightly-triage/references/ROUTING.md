# Routing: Package → Domain Label, Overrides, Known Causes

The base mapping lives in the triage skill —
`.agents/skills/triage/references/DOMAINS.md` (CI-validated by
`.github/bin/js/validate-triage-output.ts`). This file adds what nightly
triage needs on top: routing overrides, root-cause-owner rules, and the
catalogue of already-seen clusters.

## Package key → domain label (condensed)

| `#[Package]` key | Label |
|---|---|
| `framework`, `fundamentals@framework`, `framework:fundamentals` | `domain/framework` + one `component/*` |
| `checkout` | `domain/checkout` |
| `discovery`, `fundamentals@discovery` | `domain/discovery` |
| `inventory` | `domain/inventory` |
| `after-sales`, `fundamentals@after-sales` | `domain/crm-after-sales` |
| `data-services` | `service/data-intelligence` |
| `innovation`, `buyers-experience` | no label — path heuristic |

Path-mapped labels: `tests/acceptance/**` → `domain/quality-ops`;
`.github/**`, `bin/**`, CI tooling → `domain/dx-tools`.

## Team-routing overrides (beat the code marker)

| Path scope | Marker says | Route to |
|---|---|---|
| `src/Core/Framework/App/**`, `src/Core/Framework/Webhook/**`, `src/Core/System/Integration/**` (App-System) | `framework` | `domain/service-enablement` |

## Root-cause-owner rules

- A **confirmed** root cause routes all member tests to its owner. Confirmed
  means: the trace names the mechanism, or a local repro shows it.
- Ownership of the root cause = the `#[Package]` marker of the file where the
  breaking change lives (e.g. `ProductDefinition.php` → `inventory`), with
  the App-System override applied.
- Unstable mechanism (CI and local repro disagree) → do NOT move; keep with
  test owner, give it its own "needs investigation" cluster.
- Marker surprises are real: most of `src/Core/Content/Product/DataAbstractionLayer/`
  and `src/Elasticsearch/Product/` is marked `framework`, not `inventory`.
  Follow the marker for *test ownership*, the root cause for *filing*.

## Known root-cause clusters (2026-07-03 integration-major, issues #17972–#17978)

Check new failures against these before declaring a new cluster:

| Signature | Root cause | Owner |
|---|---|---|
| `Table 'root_test.import_export_profile_translation' doesn't exist` | major migration drops the table; fixtures still reference it | crm-after-sales |
| `WriteException` + `[/N/type] This value should not be blank` (incl. `[/0/children/0/type]`) | `product.type` gets `Required()` under `v6.8.0.0` — `src/Core/Content/Product/ProductDefinition.php` (flag block) | inventory |
| `FeatureException: Defining custom fields inline in manifest.xml is deprecated` — ALSO surfaces as `App synchronisation failed: Array(...same message)` via `AppSystemTestBehaviour` | App test fixtures not migrated to `Resources/config/custom-fields.xml` | service-enablement |
| `The mail.sent Event did not run` / `Expected to send order_confirmation_mail` | mail pipeline changes under `v6.8.0.0` in `SendMailAction`, `MailPayload`, `MailPayloadFactory` | crm-after-sales |
| tests expecting legacy DAL exceptions (`UnmappedFieldException`, `InvalidSortingDirectionException`, …) get `DataAbstractionLayerException` | DAL exception-class consolidation | framework |
| OpenSearch `query_shard_exception: [nested] failed to find nested object under path [visibilities]` / `[categoriesRo]` | ES index mapping change — `ElasticsearchProductDefinition` (marked `framework`) | framework |
| `FeatureException: … LineItem::setStates() is deprecated` (often as invalid data provider) | digital-products `states` → `type` transition | checkout |
| `GrantDownloadAccessActionTest`: CI `product-not-found`, local `payment-method-blocked` | UNSTABLE — mechanism differs by schema state | left with crm-after-sales, needs investigation |
| `CheapestPriceTest` assertion failures (`Product with key p.1 not found`, filter cases) | reproduced with runtime flags, mechanism TBD — NOT the type-blank error | inventory |

## Known flaky / environmental patterns (do NOT file as major breakage)

- ES relevance/minScore assertions failing only on specific OpenSearch
  versions (OS1/OS2 vs OS3) — check the job's OS version first.
- `The previous test case's transaction was not closed properly` — collateral
  of an earlier error in the same class, not an independent failure; attribute
  to the class's primary cluster.
- Order-pollution / `BackupStaticProperties` desyncs (`TypeNotRegistered`) —
  order-dependent, not major-related.
