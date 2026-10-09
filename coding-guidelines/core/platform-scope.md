# Platform scope

Shopware is standard software that runs in thousands of setups. Before a change
adds code for one of them, ask whether this is the platform's job. This page
holds the rules; the review guide for them is
[`platform-scope`](../../.agents/skills/sw-review/guides/platform-scope.md).

<a id="environment"></a>

## Environment requirements are not core's problem

The message queue worker, the scheduled task runner and storage that the
browser can reach are requirements of running Shopware. A shop without them is
misconfigured, and the fix belongs in that shop's setup or in the
documentation. Core must not move work out of a scheduled task or message
handler into a command or request, and must not add a fallback "in case the
worker does not run". Such a fallback runs in every correctly configured shop
too, so it changes behaviour for everyone to help a few. Example: theme folder
cleanup was once moved from `DeleteThemeFilesTaskHandler` into `theme:compile`
because the task did not run reliably in some environments; cached storefront
pages then loaded CSS and JS that returned 404.

<a id="options"></a>

## Options need two supported setups

A new config key, environment variable or command flag is product surface
forever: it doubles the test matrix and every later change must work with both
values. Add one only when at least two supported setups need different
behaviour. An option whose only purpose is to switch off correct behaviour for
one infrastructure variant does not qualify, and neither does changing the
default for every shop to suit that variant. Document the requirement instead,
so the operator fixes the setup. Example: a change streamed private downloads
through PHP for every shop because one hosting setup had a storage endpoint the
browser could not reach; it was closed in favour of documenting that the
endpoint must be reachable.

<a id="explicit-environment"></a>

## Explicit environment behaviour

When behaviour must differ between supported setups, make the difference
explicit: a documented config key or environment variable that the operator
sets on purpose. Do not detect the setup at runtime with `getenv()`,
`ini_get()`, `is_writable()`, `function_exists()` or `class_exists()` and
branch silently. Two shops on the same version then behave differently, and no
bug report explains why. If a requirement is missing, fail at install or boot
with a clear message. Example: read-only filesystems got the documented
`SHOPWARE_SKIP_WEBINSTALLER` variable instead of a probe whether the project
directory is writable.

<a id="root-cause"></a>

## Fix where the problem is caused

Fix a bug at the layer that causes it, not at the central place where the
symptom shows up. A fix in a shared path (sync service, kernel, request
listener) changes behaviour for every caller, including those that were
correct. The scoping rules are in the
[`shopware-change-scope`](../../.agents/skills/shopware-change-scope/SKILL.md)
skill. Example: an invalid reference in a sync request was first handled
centrally in `SyncService`; the failing operation should be marked failed at the point where
it fails, and the other operations in the request should continue.

<a id="product-specific"></a>

## Core holds no product-specific logic

Core, Storefront and Administration ship to every shop. Logic that exists for
one product, plugin or service (SaaS, Commercial, an analytics integration, one
app) belongs in that product. Core offers a generic extension point or event
instead, and keeps working when the product is not installed. Do not change
default behaviour or UI for every shop to fix a problem that only one extension
has, and check whether an existing extension mechanism covers a use case before
adding feature-specific entities or fields to core. Examples: SaaS keys
hard-coded in `ThemeCompiler`; a Storefront plugin that fails when Commercial
is not installed; a welcome text removed for every shop because one extension
showed it twice.

<a id="bundle-boundaries"></a>

## Bundle boundaries

The Storefront bundle is optional. Core and the Administration must work
headless, without it: they must not import Storefront sources, build them in,
or rely on Storefront subscribers. Code that needs the Storefront lives in the
Storefront bundle and is unavailable, or fails with a clear message, when the
bundle is missing. Storefront subscribers handle Storefront routes only; logic
that headless shops also need lives in Core. Examples: the Storefront's
Administration sources were merged into the Administration build; SEO data was
handled in a Storefront subscriber that headless shops never run.

<a id="optional-features"></a>

## Optional features cost nothing when off

An optional feature (analytics, telemetry, an integration the merchant has not
enabled) checks that it is active before it does any work. Queries, HTTP calls
or listeners on the cart, checkout, listing or request path of a switched-off
feature slow down every shop that does not use it. Example: an analytics
feature must not add queries to cart, checkout or listings.

<a id="queue-work"></a>

## Heavy work belongs on the queue

Because the worker is a requirement (see [Environment requirements](#environment)),
expensive work that a write or an app lifecycle event triggers (re-indexing,
synchronisation, bulk updates) is dispatched as a message instead of running
inside the subscriber. Done synchronously, it runs inside the merchant's save
request, slows down every write and can time out on large catalogues. Dispatch
it only when a relevant value actually changed. Examples: SEO URLs were
re-indexed synchronously in `SeoUrlTemplateChangeSubscriber`; app SEO URLs were
synchronised on every domain write.
