# Executor: `direct` (service / DAL / storefront-render — PHPUnit integration test)

Use for a symptom that is faithful WITHOUT a browser. Two flavors:
1. **Service / DAL** (the default below) — an internal service/indexer/calculation, a DAL
   behaviour, license-gated paths, or heavy domain setup. The symptom is a computed value or
   service behaviour, not an HTTP/DOM surface.
2. **Storefront server-render** — the bug is in **server-rendered storefront HTML**
   (snippet/translation text, Twig logic, a server-rendered CMS block, price/markup in the
   listing). Render the real page and assert on its HTML: faithful (it exercises the actual
   controller→CMS-resolver→Twig path) AND deterministic (no browser, no SEO/asset-build race).
   The class additionally uses
   `Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour`:
   ```php
   // Create the entities you need via getContainer() repositories in setUp() — integration
   // -test DAL writes index synchronously, so SEO URLs / CMS data are ready immediately.
   $response = $this->request('GET', '/Some-Category/', []);  // SEO URL or technical route
   $html = (string) $response->getContent();
   // Assert the HEALTHY rendered output, so it FAILS on the buggy version (→ reproduced) and
   // PASSES when healthy (→ not_reproduced):
   static::assertStringContainsString('<expected server-rendered text>', $html);
   ```
   Set `build_profile.storefront_build: false` — server HTML needs no compiled assets. Use
   this ONLY for the INITIAL server HTML; if the symptom is injected by JS after load
   (offcanvas, ajax, zoom, lazy-load) it is NOT in the rendered HTML → use `playwright`.

**Prefer the CHEAPEST faithful flavor.** A focused service/resolver assertion (flavor 1) is
far cheaper to author AND run than a full render (flavor 2): if the symptom is observable on
the resolved service/entity — e.g. a CMS element resolver returns a product whose `variation`
/`options` is wrong, a calculation is off — assert THAT directly (mirror the matching
`*Test.php` in the codebase) instead of rendering a whole page. Reach for the render only when
the symptom lives solely in the Twig output and cannot be seen on the resolved data.

## What you author
Generate `ReproTest.php` (set `script_path: "ReproTest.php"`):
- namespace `Shopware\Tests\Integration\Repro`
- `class ReproTest extends TestCase` using `IntegrationTestBehaviour` (which pulls in
  `KernelTestBehaviour`); resolve services via `$this->getContainer()`.
- **REUSE the fix PR's regression-test setup** where it exists (`./fixpr.diff`) — do NOT
  reinvent the bootstrap.
- A single test method that ASSERTS THE HEALTHY (fixed) behaviour, so it FAILS on the buggy
  version and PASSES when healthy.
- **When the SYMPTOM is a thrown exception** (the buggy version throws — e.g. a DB error
  during indexing), ALSO set `assertion.symptom_pattern` in analysis.json — a distinctive
  extended-regex for the exception text (e.g. `"1116 Too many tables"`). If the test ERRORS
  and the output matches this pattern, the executor classifies it `reproduced` even when the
  throw escaped your try/catch (DAL writes run indexers synchronously, so the symptom often
  fires during a write you considered setup). This is the safety net; still structure the
  test properly: wrap exactly the triggering action in try/catch and convert the throw into
  an assertion FAILURE:
  ```php
  try {
      $indexer->handle($message); // the action that throws on the buggy version
  } catch (\Throwable $e) {
      static::fail('symptom: ... threw ' . $e->getMessage());
  }
  ```
  Without this, the throw surfaces as a PHPUnit ERROR, which the executor classifies as
  `inconclusive` (reserved for bootstrap/compile failures) — not as the reproduction it is.
  Keep setup OUTSIDE the try/catch so genuine setup breakage still errors → `inconclusive`.
- **DAL writes run indexers SYNCHRONOUSLY in integration tests** (`EntityIndexingSubscriber`
  dispatches on entity-written). If the symptom is thrown FROM an indexer, the triggering
  **write IS the action** — wrap THAT `$repository->create()/upsert()` in the try/catch
  (mirroring the issue's "create a product"), NOT a later explicit indexer call that is
  never reached. (A real miss: the product create() at "setup" line 80 ran ProductIndexer
  inline and threw there → ERROR → inconclusive, while the wrapped indexer call below it
  was dead code.) If heavy FIXTURES would also trigger the same indexer prematurely,
  create them with indexing deferred — `$context->addState(EntityIndexerRegistry::DISABLE_INDEXING)`
  (or `EntityIndexerRegistry::USE_INDEXING_QUEUE`) during fixture writes — then perform the
  one real triggering write inside the try/catch with a normal context.

`run-direct.sh` drops the file under the shop's `tests/integration/Repro/` (PSR-4
autoload) and runs `vendor/bin/phpunit`.

## Cross-version faithfulness
Prefer STABLE service/DAL APIs so the SAME test also compiles + runs on the reported
version. If it can only compile on trunk, that is fine — the reported leg reports
`inconclusive` (not a bogus pass).

## How `run-direct.sh` classifies the PHPUnit summary
- `OK` → `not_reproduced` (healthy)
- `FAILURES!` → `reproduced` (the symptom assertion failed)
- `ERRORS!` / fatal / no-tests → `inconclusive` (couldn't bootstrap/compile — usually a
  cross-version API mismatch on the reported leg, never a bogus pass)
- anything else → `blocked`

## Comment every step
Comment the setup and the single healthy assertion (what it does + what it proves).
